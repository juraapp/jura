<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Support\Waterfall;
use App\Modules\Planning\Support\WaterfallBucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Deterministic simulation of bucket funds over time. Replays future incomes
 * through the priority waterfall, applies end-of-cycle carry-over, and buys the
 * scenario's items strictly in order at the earliest date their bucket can pay.
 * Pure: no I/O.
 */
final class ForecastEngine
{
    public function __construct(private readonly Waterfall $waterfall) {}

    /**
     * @param  list<EngineLine>  $lines  the scenario, in purchase order
     */
    public function run(EngineInput $input, array $lines): EngineResult
    {
        $buckets = [];
        $balances = [];
        foreach ($input->buckets as $bucket) {
            $buckets[$bucket->id] = $bucket;
            $balances[$bucket->id] = $bucket->balance;
        }

        $state = new class
        {
            public int $next = 0;

            public CarbonImmutable $lastPurchase;

            /** @var array<int, LineResult> */
            public array $results = [];
        };
        $state->lastPurchase = $input->start;

        $this->buy($input, $lines, $balances, $state, $input->start);

        foreach ($this->events($input, $lines) as $event) {
            if ($event['type'] === 'income') {
                $this->applyIncome($input, $buckets, $balances, $event['income']);
            } elseif ($event['type'] === 'cycle') {
                $this->closeCycle($input, $buckets[$event['bucketId']], $balances);
            }

            $this->buy($input, $lines, $balances, $state, $event['date']);
        }

        while ($state->next < count($lines)) {
            $this->recordUnbought($input, $lines[$state->next], $balances, $state);
            $state->next++;
        }

        return new EngineResult(array_values($state->results), $balances);
    }

    /**
     * @param  list<EngineLine>  $lines
     * @return list<array{date: CarbonImmutable, order: int, type: string, income?: EngineIncome, bucketId?: int}>
     */
    private function events(EngineInput $input, array $lines): array
    {
        $events = [];

        foreach ($input->incomes as $income) {
            $date = $income->date->lt($input->start) ? $input->start : $income->date;
            if ($date->lte($input->end)) {
                $events[] = ['date' => $date, 'order' => 0, 'type' => 'income', 'income' => $income];
            }
        }

        foreach ($input->buckets as $bucket) {
            if ($bucket->cycle === null || $bucket->carryOver === CarryOverPolicy::Carry) {
                continue;
            }

            $cursor = $this->cycleEnd($bucket->cycle, $input->start);
            while ($cursor->lte($input->end)) {
                $events[] = ['date' => $cursor, 'order' => 1, 'type' => 'cycle', 'bucketId' => $bucket->id];
                $cursor = $this->cycleEnd($bucket->cycle, $cursor->addDay());
            }
        }

        foreach ($lines as $line) {
            if ($line->notBefore !== null && $line->notBefore->gt($input->start) && $line->notBefore->lte($input->end)) {
                $events[] = ['date' => $line->notBefore, 'order' => 2, 'type' => 'check'];
            }
        }

        usort($events, fn (array $a, array $b) => [$a['date']->getTimestamp(), $a['order']] <=> [$b['date']->getTimestamp(), $b['order']]);

        return $events;
    }

    private function cycleEnd(BudgetPeriodType $cycle, CarbonImmutable $from): CarbonImmutable
    {
        return $cycle === BudgetPeriodType::Yearly ? $from->endOfYear()->startOfDay() : $from->endOfMonth()->startOfDay();
    }

    /**
     * @param  array<int, EngineBucket>  $buckets
     * @param  array<int, Money>  $balances
     */
    private function applyIncome(EngineInput $input, array $buckets, array &$balances, EngineIncome $income): void
    {
        $anyRule = false;
        foreach ($buckets as $bucket) {
            $anyRule = $anyRule || $bucket->rule !== null;
        }

        if (! $anyRule) {
            return;
        }

        $inputs = [];
        foreach ($input->buckets as $bucket) {
            if ($bucket->acceptsAccount($income->accountId)) {
                $inputs[] = new WaterfallBucket($bucket->id, $bucket->rule, $bucket->value, $bucket->room($balances[$bucket->id]));
            }
        }

        $result = $this->waterfall->distribute($income->amount, $inputs);

        foreach ($result['allocations'] as $bucketId => $amount) {
            $balances[$bucketId] = $balances[$bucketId]->add($amount);
        }

        $default = $input->defaultBucket();
        if ($result['leftover']->isPositive() && $default !== null && $default->acceptsAccount($income->accountId)) {
            $balances[$default->id] = $balances[$default->id]->add($result['leftover']);
        }
    }

    /**
     * @param  array<int, Money>  $balances
     */
    private function closeCycle(EngineInput $input, EngineBucket $bucket, array &$balances): void
    {
        $leftover = $balances[$bucket->id];
        if (! $leftover->isPositive()) {
            return;
        }

        $target = $bucket->carryOver === CarryOverPolicy::Overflow && $bucket->overflowBucketId !== null
            ? $bucket->overflowBucketId
            : $input->defaultBucket()?->id;

        if ($target === null || $target === $bucket->id || ! isset($balances[$target])) {
            return;
        }

        $balances[$bucket->id] = $balances[$bucket->id]->subtract($leftover);
        $balances[$target] = $balances[$target]->add($leftover);
    }

    /**
     * Buys as many scenario items as are due on this date, in order.
     *
     * @param  list<EngineLine>  $lines
     * @param  array<int, Money>  $balances
     */
    private function buy(EngineInput $input, array $lines, array &$balances, object $state, CarbonImmutable $date): void
    {
        while ($state->next < count($lines)) {
            $line = $lines[$state->next];

            if ($line->blocked !== null) {
                $this->recordUnbought($input, $line, $balances, $state);
                $state->next++;

                continue;
            }

            $earliest = $state->lastPurchase;
            if ($line->notBefore !== null && $line->notBefore->gt($earliest)) {
                $earliest = $line->notBefore;
            }

            $bucketId = $line->bucketId ?? $input->defaultBucket()?->id;

            if ($date->lt($earliest) || $bucketId === null || ! isset($balances[$bucketId]) || $balances[$bucketId]->lessThan($line->price)) {
                return;
            }

            $balances[$bucketId] = $balances[$bucketId]->subtract($line->price);
            $state->lastPurchase = $date;
            $state->results[$state->next] = new LineResult(
                $line->itemId, $line->name, $line->price, $date, (int) round($input->start->diffInDays($date)), Money::zero(), null,
                $line->desiredBy !== null && $date->gt($line->desiredBy), $line->importance,
            );
            $state->next++;
        }
    }

    /**
     * @param  array<int, Money>  $balances
     */
    private function recordUnbought(EngineInput $input, EngineLine $line, array $balances, object $state): void
    {
        $bucketId = $line->bucketId ?? $input->defaultBucket()?->id;
        $available = $bucketId !== null && isset($balances[$bucketId]) ? $balances[$bucketId] : Money::zero();
        $missing = $line->price->subtract($available);

        $state->results[$state->next] = new LineResult(
            $line->itemId, $line->name, $line->price, null, null,
            $line->blocked !== null || $missing->isPositive() ? ($line->blocked !== null ? $line->price : $missing) : Money::zero(),
            $line->blocked, false, $line->importance,
        );
    }
}
