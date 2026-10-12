<?php

namespace App\Modules\Planning\Support\Forecast;

/**
 * Proposes purchase orders. The score is the importance-weighted number of
 * days each item waits (plus a penalty for waiting past a "want it by" date),
 * and a large penalty for items that cannot be afforded within the horizon, so
 * the best order buys what you want most, as soon as the money allows.
 */
final class ScenarioSuggester
{
    /** Up to this many items every ordering is tried; beyond it a local search refines the heuristics. */
    private const EXHAUSTIVE_LIMIT = 6;

    /** Extra days charged (per importance point) for an item that is never bought within the horizon. */
    private const UNBOUGHT_PENALTY_DAYS = 180;

    private const LATE_WEIGHT = 0.5;

    public function __construct(private readonly ForecastEngine $engine) {}

    /**
     * @param  list<EngineLine>  $items
     * @return list<Suggestion> best first, at most $limit distinct orderings
     */
    public function suggest(EngineInput $input, array $items, int $limit = 4): array
    {
        if ($items === []) {
            return [];
        }

        $heuristics = $this->heuristics($input, $items);
        $best = count($items) <= self::EXHAUSTIVE_LIMIT
            ? $this->exhaustive($input, $items, $heuristics['importance'])
            : $this->localSearch($input, $items, $heuristics);

        $candidates = ['best' => $best] + $heuristics;
        $suggestions = [];
        $seen = [];

        foreach ($candidates as $strategy => $order) {
            $key = implode(',', $order);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $result = $this->engine->run($input, $this->linesFor($items, $order));
            $suggestions[] = new Suggestion($strategy, $order, $result, $this->score($input, $result));
        }

        $first = array_shift($suggestions);
        usort($suggestions, fn (Suggestion $a, Suggestion $b) => $a->score <=> $b->score);

        return array_slice([$first, ...$suggestions], 0, $limit);
    }

    public function score(EngineInput $input, EngineResult $result): float
    {
        $horizonDays = (int) round($input->start->diffInDays($input->end));
        $score = 0.0;

        foreach ($result->lines as $line) {
            if ($line->purchaseDate === null) {
                $score += $line->importance * ($horizonDays + self::UNBOUGHT_PENALTY_DAYS);

                continue;
            }

            $score += $line->importance * $line->delayDays;
        }

        return $score + $this->latenessPenalty($result);
    }

    private function latenessPenalty(EngineResult $result): float
    {
        $penalty = 0.0;

        foreach ($result->lines as $line) {
            if ($line->late) {
                $penalty += $line->importance * self::LATE_WEIGHT * 30;
            }
        }

        return $penalty;
    }

    /**
     * @param  list<EngineLine>  $items
     * @return array<string, list<int>>
     */
    private function heuristics(EngineInput $input, array $items): array
    {
        $standalone = [];
        foreach ($items as $item) {
            $line = $this->engine->run($input, [$item])->lines[0];
            $standalone[$item->itemId] = $line->purchaseDate?->getTimestamp() ?? PHP_INT_MAX;
        }

        $sort = function (callable $compare) use ($items): array {
            $sorted = $items;
            usort($sorted, fn (EngineLine $a, EngineLine $b) => $compare($a, $b) ?: $a->itemId <=> $b->itemId);

            return array_map(fn (EngineLine $line) => $line->itemId, $sorted);
        };

        return [
            'importance' => $sort(fn ($a, $b) => [$b->importance, $a->price->toFloat()] <=> [$a->importance, $b->price->toFloat()]),
            'cheapest' => $sort(fn ($a, $b) => [$a->price->toFloat(), $b->importance] <=> [$b->price->toFloat(), $a->importance]),
            'deadline' => $sort(fn ($a, $b) => [$a->desiredBy?->getTimestamp() ?? PHP_INT_MAX, $b->importance] <=> [$b->desiredBy?->getTimestamp() ?? PHP_INT_MAX, $a->importance]),
            'affordable' => $sort(fn ($a, $b) => [$standalone[$a->itemId], $b->importance] <=> [$standalone[$b->itemId], $a->importance]),
        ];
    }

    /**
     * Tries every ordering; ties keep the starting order (the importance ordering).
     *
     * @param  list<EngineLine>  $items
     * @param  list<int>  $start
     * @return list<int>
     */
    private function exhaustive(EngineInput $input, array $items, array $start): array
    {
        $best = $start;
        $bestScore = $this->scoreOrder($input, $items, $start);

        foreach ($this->permutations($start) as $order) {
            $score = $this->scoreOrder($input, $items, $order);
            if ($score < $bestScore - 1e-9) {
                $best = $order;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Starts from the best heuristic and keeps swapping neighbours while that improves the score.
     *
     * @param  list<EngineLine>  $items
     * @param  array<string, list<int>>  $heuristics
     * @return list<int>
     */
    private function localSearch(EngineInput $input, array $items, array $heuristics): array
    {
        $best = null;
        $bestScore = INF;
        foreach ($heuristics as $order) {
            $score = $this->scoreOrder($input, $items, $order);
            if ($score < $bestScore) {
                $best = $order;
                $bestScore = $score;
            }
        }

        for ($pass = 0; $pass < 50; $pass++) {
            $improved = false;

            for ($i = 0; $i < count($best) - 1; $i++) {
                $candidate = $best;
                [$candidate[$i], $candidate[$i + 1]] = [$candidate[$i + 1], $candidate[$i]];
                $score = $this->scoreOrder($input, $items, $candidate);

                if ($score < $bestScore - 1e-9) {
                    $best = $candidate;
                    $bestScore = $score;
                    $improved = true;
                }
            }

            if (! $improved) {
                break;
            }
        }

        return $best;
    }

    /**
     * @param  list<EngineLine>  $items
     * @param  list<int>  $order
     */
    private function scoreOrder(EngineInput $input, array $items, array $order): float
    {
        return $this->score($input, $this->engine->run($input, $this->linesFor($items, $order)));
    }

    /**
     * @param  list<EngineLine>  $items
     * @param  list<int>  $order
     * @return list<EngineLine>
     */
    private function linesFor(array $items, array $order): array
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[$item->itemId] = $item;
        }

        return array_map(fn (int $id) => $byId[$id], $order);
    }

    /**
     * @param  list<int>  $items
     * @return \Generator<list<int>>
     */
    private function permutations(array $items): \Generator
    {
        if (count($items) <= 1) {
            yield $items;

            return;
        }

        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);

            foreach ($this->permutations(array_values($rest)) as $permutation) {
                yield [$item, ...$permutation];
            }
        }
    }
}
