<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\BucketCycle;
use App\Modules\Planning\Support\Forecast\CashTimeline;
use App\Modules\Planning\Support\Forecast\EngineBucket;
use App\Modules\Planning\Support\Forecast\EngineFlow;
use App\Modules\Planning\Support\Forecast\FlowKind;
use App\Modules\Planning\Support\Forecast\ForecastCoverage;
use App\Modules\Planning\Support\ProjectedItem;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns the user's real money and plans into forecast input for one currency: on-budget cash (active, on-budget
 * accounts), every bucket's envelope, and the open planned money (PlannedOccurrences) that moves on-budget cash.
 * Incomes count when the view trusts them and they land in the budget (an on-budget account, or no account);
 * expenses count when they leave the budget (or have no account); a planned transfer counts only when it crosses
 * the budget's edge. Projected interest is left out. Memoized per instance: a service holding one builds once per
 * request (the container hands every service its own instance, so data saved in between is never served stale).
 */
class ForecastInputBuilder
{
    /** @var array<string, ForecastInputs> */
    private array $built = [];

    public function __construct(
        private readonly Balances $balances,
        private readonly BucketLedger $funds,
        private readonly PlannedOccurrences $occurrences,
    ) {}

    public function build(User $user, string $currency, ForecastView $view, CarbonInterface $until): ForecastInputs
    {
        $start = CarbonImmutable::instance($user->today())->startOfDay();
        $until = CarbonImmutable::instance($until)->startOfDay();
        $currency = strtoupper($currency);
        $key = implode('|', [$user->id, $currency, $view->value, $start->format('Y-m-d'), $until->format('Y-m-d')]);

        return $this->built[$key] ??= $this->make($user, $currency, $view, $start, $until);
    }

    private function make(User $user, string $currency, ForecastView $view, CarbonImmutable $start, CarbonImmutable $until): ForecastInputs
    {
        $accounts = $this->balances->budgetBalances($user);
        $onBudget = array_fill_keys($accounts->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $startCash = $accounts->where('currency', $currency)
            ->reduce(fn (Money $sum, array $account) => $sum->add($account['balance']), Money::zero());

        [$flows, $coverage] = $this->flows($user, $currency, $view, $start, $until, $onBudget);
        [$buckets, $names] = $this->buckets($user, $currency, $start);

        return new ForecastInputs($start, $until, $startCash, $buckets, $flows, CashTimeline::build($start, $startCash, $flows), $names, $coverage);
    }

    /**
     * @param  array<int, true>  $onBudget
     * @return array{0: list<EngineFlow>, 1: ForecastCoverage}
     */
    private function flows(User $user, string $currency, ForecastView $view, CarbonImmutable $start, CarbonImmutable $until, array $onBudget): array
    {
        $inBudget = fn (?int $accountId) => $accountId === null || isset($onBudget[$accountId]);

        $flows = [];
        $counted = 0;
        $countedAmount = Money::zero();
        $total = 0;
        $totalAmount = Money::zero();
        $overdue = 0;
        $offBudget = 0;
        $offBudgetAmount = Money::zero();
        $seq = 0;

        foreach ($this->occurrences->open($user, $until, $currency) as $item) {
            /** @var ProjectedItem $item */
            $date = $item->effectiveDate($start);
            $ref = $item->eventId !== null ? 'event:'.$item->eventId : 'series:'.$item->seriesId.':'.$item->date->format('Y-m-d');

            if ($item->isIncome()) {
                if (! $inBudget($item->accountId)) {
                    $offBudget++;
                    $offBudgetAmount = $offBudgetAmount->add($item->amount);

                    continue;
                }

                $total++;
                $totalAmount = $totalAmount->add($item->amount);
                if (! $view->includesIncome($item->certainty)) {
                    continue;
                }

                $counted++;
                $countedAmount = $countedAmount->add($item->amount);
                if ($item->date->lt($start)) {
                    $overdue++;
                }

                $overrides = [];
                foreach ($item->allocationOverrides ?? [] as $row) {
                    $overrides[(int) $row['bucket_id']] = Money::of($row['amount']);
                }

                $flows[] = new EngineFlow($date, $seq++, FlowKind::Income, $item->amount, $item->accountId, null, $overrides, $ref);
            } elseif ($item->isExpense()) {
                if ($inBudget($item->accountId)) {
                    $flows[] = new EngineFlow($date, $seq++, FlowKind::Expense, $item->amount, $item->accountId, $item->categoryId, [], $ref);
                }
            } elseif ($item->isTransfer()) {
                $fromIn = $item->accountId !== null && isset($onBudget[$item->accountId]);
                $toIn = $item->counterAccountId !== null && isset($onBudget[$item->counterAccountId]);

                if ($fromIn !== $toIn) {
                    $flows[] = new EngineFlow($date, $seq++, FlowKind::Boundary, $fromIn ? $item->amount->negate() : $item->amount, $fromIn ? $item->accountId : $item->counterAccountId, null, [], $ref);
                }
            }
        }

        usort($flows, fn (EngineFlow $a, EngineFlow $b) => [$a->date->format('Y-m-d'), $a->seq] <=> [$b->date->format('Y-m-d'), $b->seq]);

        return [$flows, new ForecastCoverage($view->value, $counted, $countedAmount, $total, $totalAmount, $overdue, $offBudget, $offBudgetAmount)];
    }

    /**
     * @return array{0: list<EngineBucket>, 1: array<int, string>}
     */
    private function buckets(User $user, string $currency, CarbonImmutable $start): array
    {
        $buckets = Bucket::forUser($user)->where('currency', $currency)->inPriorityOrder()->get();
        $envelopes = $this->funds->availableMany($buckets);
        $overrides = $this->capOverrides($user, $buckets->modelKeys());

        $names = [];
        $engineBuckets = [];
        foreach ($buckets as $bucket) {
            $names[$bucket->id] = $bucket->name;
            $since = $this->funds->loadedFundedSince($bucket);
            $period = $bucket->cyclePeriod();
            $currentStart = $period?->startOf($start->toDateTimeImmutable())->format('Y-m-d');

            $engineBuckets[] = new EngineBucket(
                id: $bucket->id,
                rule: $bucket->fill_rule,
                value: $bucket->fill_value !== null ? (string) $bucket->fill_value : null,
                cap: $bucket->cap_amount,
                isSavings: $bucket->kind === BucketKind::Savings,
                envelope: $envelopes[$bucket->id] ?? Money::zero(),
                allowedAccountIds: $bucket->allowed_account_ids,
                cycle: $bucket->cycle,
                carryOver: $bucket->carry_over,
                overflowBucketId: $bucket->overflow_bucket_id,
                cycleEvery: (int) ($bucket->cycle_every ?: 1),
                cycleAnchor: $this->date($bucket->cycle_anchor),
                receivedThisCycle: $period !== null ? $this->funds->receivedInCycle($bucket, $start) : null,
                stepUpValue: $bucket->step_up_value !== null ? (string) $bucket->step_up_value : null,
                stepUpAfterBucketId: $bucket->step_up_after_bucket_id,
                completesOnPurchase: (bool) $bucket->completes_on_purchase,
                completedOn: $this->date($bucket->completed_on),
                isFunded: $bucket->funded_since !== null,
                fundedSince: $this->date($since),
                categoryId: $bucket->kind === BucketKind::Spending ? $bucket->category_id : null,
                capOverrides: $currentStart === null ? [] : array_filter(
                    $overrides[$bucket->id] ?? [],
                    fn (string $cycleStart) => $cycleStart >= $currentStart,
                    ARRAY_FILTER_USE_KEY,
                ),
                trackingStartedOn: $this->date($bucket->tracking_started_on),
                stepUpActive: $bucket->step_up_value !== null && $this->funds->fillValueFor($bucket, $start) === (string) $bucket->step_up_value,
            );
        }

        return [$engineBuckets, $names];
    }

    /**
     * @param  list<int>  $bucketIds
     * @return array<int, array<string, Money>> bucket id => cycle start => cap
     */
    private function capOverrides(User $user, array $bucketIds): array
    {
        if ($bucketIds === []) {
            return [];
        }

        $overrides = [];
        BucketCycle::forUser($user)
            ->whereIn('bucket_id', $bucketIds)
            ->whereNotNull('cap_override')
            ->get(['bucket_id', 'starts_on', 'cap_override'])
            ->each(function (BucketCycle $cycle) use (&$overrides) {
                $overrides[$cycle->bucket_id][$cycle->starts_on->toDateString()] = $cycle->cap_override;
            });

        return $overrides;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value instanceof CarbonInterface ? $value->toDateString() : (string) $value)->startOfDay();
    }
}
