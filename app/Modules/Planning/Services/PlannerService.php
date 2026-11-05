<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\EngineLine;
use App\Modules\Planning\Support\Forecast\EngineResult;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Planning\Support\Forecast\ScenarioSuggester;
use Carbon\CarbonImmutable;

/**
 * Turns the user's real buckets, planned incomes and wish list into engine
 * input, runs the forecast engine and presents the result for the planner page.
 */
class PlannerService
{
    public function __construct(
        private readonly ForecastInputBuilder $builder,
        private readonly ForecastEngine $engine,
        private readonly ScenarioSuggester $suggester,
        private readonly LedgerQuery $ledger,
    ) {}

    public function context(User $user, string $currency, ForecastView $view, int $horizonMonths): PlannerContext
    {
        $start = CarbonImmutable::instance($user->today())->startOfDay();
        $end = $start->addMonthsNoOverflow($horizonMonths);
        $inputs = $this->builder->build($user, $currency, $view, $end);

        $balances = [];
        $hasFillRules = false;
        foreach ($inputs->buckets as $bucket) {
            $balances[$bucket->id] = $bucket->envelope->toFloat();
            $hasFillRules = $hasFillRules || $bucket->rule !== null;
        }

        return new PlannerContext(
            new EngineInput($start, $end, $inputs->startCash, $inputs->buckets, $inputs->flows, $inputs->timeline),
            $inputs->bucketNames,
            $balances,
            $hasFillRules,
            $inputs->coverage,
            $inputs->startReadyToAssign(),
        );
    }

    /**
     * @param  list<array{item_id: int|string, not_before?: string|null}>  $rows  in purchase order
     * @return list<EngineLine>
     */
    public function lines(User $user, string $currency, PlannerContext $context, array $rows): array
    {
        $items = WishlistItem::forUser($user)
            ->whereIn('id', array_column($rows, 'item_id'))
            ->where('status', WishlistStatus::Wanted->value)
            ->where('currency', $currency)
            ->get()
            ->keyBy('id');

        $buckets = [];
        foreach ($context->input->buckets as $bucket) {
            $buckets[$bucket->id] = $bucket;
        }

        $lines = [];
        foreach ($rows as $row) {
            $item = $items->get((int) $row['item_id']);
            if ($item === null) {
                continue;
            }

            $bucket = $item->bucket_id !== null ? ($buckets[$item->bucket_id] ?? null) : null;
            $blocked = null;
            if (! empty($item->allowed_account_ids) && $bucket !== null && ! empty($bucket->allowedAccountIds)
                && array_intersect($item->allowed_account_ids, $bucket->allowedAccountIds) === []) {
                $blocked = 'accounts';
            } elseif (! empty($item->allowed_account_ids)
                && collect($item->allowed_account_ids)->every(fn ($accountId) => ! $this->ledger->isOnBudget($user->id, (int) $accountId))) {
                $blocked = 'off_budget';
            }

            $warnings = [];
            if ($bucket !== null && $bucket->roomApplies()) {
                $cap = $bucket->capOn($context->input->start);
                $most = $cap !== null && $cap->greaterThan($bucket->envelope) ? $cap : $bucket->envelope;
                if ($cap !== null && $most->lessThan($item->price)) {
                    $warnings[] = 'cap_below_price';
                }
            }

            $lines[] = new EngineLine(
                $item->id,
                $item->name,
                $item->price,
                $item->bucket_id,
                ! empty($row['not_before']) ? CarbonImmutable::parse($row['not_before']) : null,
                $item->desired_by ? CarbonImmutable::parse($item->desired_by->toDateString()) : null,
                $item->importance,
                $blocked,
                warnings: $warnings,
            );
        }

        return $lines;
    }

    /**
     * @param  list<EngineLine>  $lines
     * @return array<string, mixed>
     */
    public function simulate(PlannerContext $context, array $lines): array
    {
        $result = $this->engine->run($context->input, $lines);

        return $this->present($context, $result, $this->suggester->score($context->input, $result));
    }

    /**
     * @param  list<EngineLine>  $items  candidates, in any order
     * @return list<array<string, mixed>>
     */
    public function suggest(PlannerContext $context, array $items, int $limit = 4): array
    {
        return array_map(
            fn ($suggestion) => $this->present($context, $suggestion->result, $suggestion->score) + [
                'strategy' => $suggestion->strategy,
                'label' => __('planner.strategy.'.$suggestion->strategy),
                'order' => $suggestion->order,
            ],
            $this->suggester->suggest($context->input, $items, $limit),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PlannerContext $context, EngineResult $result, float $score): array
    {
        return [
            'lines' => array_map(fn ($line) => [
                'itemId' => $line->itemId,
                'name' => $line->name,
                'price' => $line->price->toFloat(),
                'purchaseDate' => $line->purchaseDate?->toDateString(),
                'delayDays' => $line->delayDays,
                'shortfall' => $line->shortfall->toFloat(),
                'blocked' => $line->blocked,
                'late' => $line->late,
                'importance' => $line->importance,
                'fromBucket' => $line->fromBucket->toFloat(),
                'fromFree' => $line->fromFree->toFloat(),
                'limitedBy' => $line->limitedBy,
                'parked' => $line->parked,
                'freeDipsOn' => $line->freeDipsOn?->toDateString(),
                'warnings' => $line->warnings,
            ], $result->lines),
            'buckets' => collect($context->bucketNames)->map(fn (string $name, int $id) => [
                'id' => $id,
                'name' => $name,
                'startBalance' => $context->startBalances[$id],
                'endBalance' => $result->endBalances[$id]->toFloat(),
            ])->values()->all(),
            'readyToAssign' => [
                'start' => $context->startReadyToAssign->toFloat(),
                'end' => $result->endReadyToAssign->toFloat(),
            ],
            'timeline' => $this->monthEnds($context, $result),
            'incomes' => [
                'view' => $context->coverage->view,
                'counted' => $context->coverage->incomesCounted,
                'total' => $context->coverage->incomesTotal,
                'countedAmount' => $context->coverage->countedAmount->toFloat(),
                'totalAmount' => $context->coverage->totalAmount->toFloat(),
                'overdue' => $context->coverage->overdueCounted,
                'offBudget' => $context->coverage->offBudgetIncomes,
                'offBudgetAmount' => $context->coverage->offBudgetAmount->toFloat(),
                'allocated' => $result->incomes->allocated->toFloat(),
                'leftFree' => $result->incomes->leftFree->toFloat(),
            ],
            'score' => round($score, 1),
            'hasFillRules' => $context->hasFillRules,
        ];
    }

    /**
     * Cash and ready to assign at the end of every month of the horizon (the last point: the horizon's end).
     *
     * @return list<array{date: string, cash: float, free: float}>
     */
    private function monthEnds(PlannerContext $context, EngineResult $result): array
    {
        $points = [];
        $end = $context->input->end;
        for ($month = $context->input->start->endOfMonth()->startOfDay(); ; $month = $month->addDay()->endOfMonth()->startOfDay()) {
            $date = $month->gt($end) ? $end : $month;
            $point = $result->pointAt($date);
            if ($point !== null) {
                $points[] = ['date' => $date->toDateString(), 'cash' => $point->cash->toFloat(), 'free' => $point->free->toFloat()];
            }
            if ($month->gte($end)) {
                break;
            }
        }

        return $points;
    }
}
