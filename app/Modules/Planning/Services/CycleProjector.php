<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Support\CyclePeriod;
use App\Modules\Planning\Support\Waterfall;
use App\Modules\Planning\Support\WaterfallBucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * What the current cycle of each bucket is expected to look like: the provisional fill from
 * planned income still to come (never stored, never touches a balance), and what is expected
 * to overflow when the cycle ends if nothing more is spent than what is planned.
 */
class CycleProjector
{
    public function __construct(
        private readonly BucketLedger $funds,
        private readonly Waterfall $waterfall,
        private readonly PlannedEventService $planned,
    ) {}

    /**
     * @return array<int, array{start: Carbon, end: Carbon, available: Money, provisional: Money, projectedEnd: Money, projectedRelease: Money, projectedIn: Money, projectedClose: Money}>
     *                                                                                                                                                                                      keyed by bucket id
     */
    public function project(User $user, string $currency, ForecastView $view = ForecastView::Conservative, ?CarbonInterface $today = null): array
    {
        $today = Carbon::instance($today ?? now())->startOfDay();
        $buckets = Bucket::forUser($user)->where('currency', $currency)->inPriorityOrder()->get();

        $windows = [];
        $balances = [];
        foreach ($buckets as $bucket) {
            $period = $bucket->cyclePeriod() ?? new CyclePeriod(BudgetPeriodType::Monthly);
            $moment = $today->toDateTimeImmutable();
            $windows[$bucket->id] = [Carbon::instance($period->startOf($moment)), Carbon::instance($period->endOf($moment))];
            $balances[$bucket->id] = $this->funds->available($bucket, $today);
        }

        $horizon = collect($windows)->map(fn (array $window) => $window[1])->max() ?? $today;
        $fills = array_fill_keys($buckets->pluck('id')->all(), Money::zero());
        $atWindowEnd = $balances;

        $incomes = PlannedEvent::forUser($user)->open()
            ->where('type', TransactionType::Income->value)
            ->where('currency', $currency)
            ->where('date', '<=', $horizon->toDateString())
            ->orderBy('date')->orderBy('id')
            ->get()
            ->filter(fn (PlannedEvent $event) => $view->includesIncome($event->certainty));

        /** @var array<int, array<string, Money>> income received per bucket and cycle start, real or simulated */
        $received = [];
        foreach ($buckets as $bucket) {
            if ($bucket->cyclePeriod() !== null) {
                $received[$bucket->id] = [$windows[$bucket->id][0]->format('Y-m-d') => $this->funds->receivedInCycle($bucket, $today) ?? Money::zero()];
            }
        }

        $anyRule = $buckets->contains(fn (Bucket $bucket) => $bucket->fill_rule !== null);
        $default = $buckets->firstWhere('is_default', true);

        foreach ($incomes as $event) {
            $date = $event->date->lt($today) ? $today : Carbon::instance($event->date);
            $overrides = collect($event->allocation_overrides ?? [])
                ->mapWithKeys(fn (array $row) => [(int) $row['bucket_id'] => Money::of($row['amount'])])
                ->all();

            if ($overrides === [] && ! $anyRule) {
                continue;
            }

            $inputs = $buckets
                ->filter(fn (Bucket $bucket) => $bucket->acceptsAccount($event->account_id) && ! $bucket->isCompletedOn($date))
                ->map(fn (Bucket $bucket) => new WaterfallBucket(
                    $bucket->id,
                    $bucket->fill_rule,
                    $this->funds->fillValueFor($bucket, $date),
                    $this->room($bucket, $balances[$bucket->id], $date),
                    $this->receivedOn($bucket, $received, $date),
                ))->values()->all();

            $result = $this->waterfall->distribute($event->amount, $inputs, $overrides);
            $allocations = $result['allocations'];

            if ($result['leftover']->isPositive() && $default !== null && $default->acceptsAccount($event->account_id)) {
                $allocations[$default->id] = ($allocations[$default->id] ?? Money::zero())->add($result['leftover']);
            }

            foreach ($allocations as $bucketId => $amount) {
                $balances[$bucketId] = $balances[$bucketId]->add($amount);

                if (isset($received[$bucketId])) {
                    $key = $buckets->firstWhere('id', $bucketId)->cyclePeriod()->startOf($date->toDateTimeImmutable())->format('Y-m-d');
                    $received[$bucketId][$key] = ($received[$bucketId][$key] ?? Money::zero())->add($amount);
                }

                if ($date->lte($windows[$bucketId][1])) {
                    $fills[$bucketId] = $fills[$bucketId]->add($amount);
                    $atWindowEnd[$bucketId] = $atWindowEnd[$bucketId]->add($amount);
                }
            }
        }

        $projection = [];
        $incoming = array_fill_keys($buckets->pluck('id')->all(), Money::zero());

        foreach ($buckets as $bucket) {
            [$start, $end] = $windows[$bucket->id];
            $expected = $atWindowEnd[$bucket->id];

            if ($bucket->kind === BucketKind::Spending && $bucket->category_id !== null) {
                $expected = $expected->subtract($this->planned->plannedExpenseTotal($bucket->category_id, $currency, $today, $end));
            }

            $release = Money::zero();
            if ($bucket->cycle !== null && $bucket->carry_over !== CarryOverPolicy::Carry && $expected->isPositive()) {
                $targetId = $bucket->carry_over === CarryOverPolicy::Overflow ? $bucket->overflow_bucket_id : $default?->id;
                if ($targetId !== null && $targetId !== $bucket->id && isset($incoming[$targetId])) {
                    $release = $expected;
                    $incoming[$targetId] = $incoming[$targetId]->add($release);
                }
            }

            $projection[$bucket->id] = [
                'start' => $start,
                'end' => $end,
                'available' => $this->funds->available($bucket, $today),
                'provisional' => $fills[$bucket->id],
                'projectedEnd' => $expected,
                'projectedRelease' => $release,
            ];
        }

        foreach ($projection as $bucketId => $row) {
            $projection[$bucketId]['projectedIn'] = $incoming[$bucketId];
            $projection[$bucketId]['projectedClose'] = $row['projectedEnd']->subtract($row['projectedRelease'])->add($incoming[$bucketId]);
        }

        return $projection;
    }

    /**
     * How much more a bucket can take: up to its limit/target for savings and fill-to-target rules.
     */
    private function room(Bucket $bucket, Money $available, CarbonInterface $date): ?Money
    {
        $cap = $bucket->effectiveCap($date);

        if ($cap === null || ($bucket->fill_rule !== FillRule::FillToTarget && $bucket->kind !== BucketKind::Savings)) {
            return null;
        }

        $missing = $cap->subtract($available);

        return $missing->isPositive() ? $missing : Money::zero();
    }

    /**
     * @param  array<int, array<string, Money>>  $received
     */
    private function receivedOn(Bucket $bucket, array $received, CarbonInterface $date): ?Money
    {
        if (! isset($received[$bucket->id])) {
            return null;
        }

        $key = $bucket->cyclePeriod()->startOf($date->toDateTimeImmutable())->format('Y-m-d');

        return $received[$bucket->id][$key] ?? Money::zero();
    }
}
