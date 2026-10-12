<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Support\Forecast\EngineBucket;
use App\Modules\Planning\Support\Forecast\EngineIncome;
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
        private readonly BucketLedger $funds,
        private readonly ForecastEngine $engine,
        private readonly ScenarioSuggester $suggester,
    ) {}

    public function context(User $user, string $currency, ForecastView $view, int $horizonMonths): PlannerContext
    {
        $start = CarbonImmutable::today();
        $end = $start->addMonthsNoOverflow($horizonMonths);

        $buckets = Bucket::forUser($user)->where('currency', $currency)->inPriorityOrder()->get();

        $names = [];
        $balances = [];
        $engineBuckets = $buckets->map(function (Bucket $bucket) use (&$names, &$balances, $start) {
            $balance = $this->funds->available($bucket);
            $names[$bucket->id] = $bucket->name;
            $balances[$bucket->id] = $balance->toFloat();

            return new EngineBucket(
                $bucket->id,
                $bucket->fill_rule,
                $bucket->fill_value !== null ? (string) $bucket->fill_value : null,
                $bucket->cap_amount,
                $bucket->kind === BucketKind::Savings,
                $balance,
                $bucket->allowed_account_ids,
                $bucket->cycle,
                $bucket->carry_over,
                $bucket->overflow_bucket_id,
                $bucket->is_default,
                (int) $bucket->cycle_every,
                $bucket->cycle_anchor !== null ? CarbonImmutable::parse($bucket->cycle_anchor->toDateString()) : null,
                $this->funds->receivedInCycle($bucket, $start),
            );
        })->values()->all();

        $incomes = PlannedEvent::forUser($user)
            ->open()
            ->where('type', TransactionType::Income->value)
            ->where('currency', $currency)
            ->where('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->get()
            ->filter(fn (PlannedEvent $event) => $view->includesIncome($event->certainty))
            ->map(fn (PlannedEvent $event) => new EngineIncome(CarbonImmutable::parse($event->date->toDateString()), $event->amount, $event->account_id))
            ->values()
            ->all();

        return new PlannerContext(
            new EngineInput($start, $end, $engineBuckets, $incomes),
            $names,
            $balances,
            $buckets->contains(fn (Bucket $bucket) => $bucket->fill_rule !== null),
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
        $defaultId = $context->input->defaultBucket()?->id;

        $lines = [];
        foreach ($rows as $row) {
            $item = $items->get((int) $row['item_id']);
            if ($item === null) {
                continue;
            }

            $bucket = $buckets[$item->bucket_id ?? $defaultId] ?? null;
            $blocked = null;
            if (! empty($item->allowed_account_ids) && $bucket !== null && ! empty($bucket->allowedAccountIds)
                && array_intersect($item->allowed_account_ids, $bucket->allowedAccountIds) === []) {
                $blocked = 'accounts';
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
            ], $result->lines),
            'buckets' => collect($context->bucketNames)->map(fn (string $name, int $id) => [
                'id' => $id,
                'name' => $name,
                'startBalance' => $context->startBalances[$id],
                'endBalance' => $result->endBalances[$id]->toFloat(),
            ])->values()->all(),
            'score' => round($score, 1),
            'hasFillRules' => $context->hasFillRules,
        ];
    }
}
