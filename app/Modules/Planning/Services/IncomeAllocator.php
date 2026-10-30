<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Support\Waterfall;
use App\Modules\Planning\Support\WaterfallBucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Splits a recorded income across the user's buckets. Runs automatically only
 * when at least one bucket has a fill rule, or when a planned income carries
 * explicit per-bucket amounts. An income that realizes (or is linked to) a plan
 * with explicit amounts always uses them, also when it is allocated again after
 * an edit, a bulk change or a restore from the Trash.
 */
class IncomeAllocator
{
    /** @var array<int, Money> bucket id => amount, set while a planned income is being realized */
    private array $pendingOverrides = [];

    public function __construct(
        private readonly BucketLedger $funds,
        private readonly Waterfall $waterfall,
    ) {}

    /**
     * Runs a callback during which any income that gets recorded uses these explicit amounts instead of the rules.
     *
     * @param  list<array{bucket_id: int|string, amount: int|float|string}>  $overrides
     */
    public function withOverrides(array $overrides, Closure $callback): mixed
    {
        $this->pendingOverrides = collect($overrides)
            ->mapWithKeys(fn (array $row) => [(int) $row['bucket_id'] => Money::of($row['amount'])])
            ->all();

        try {
            return $callback();
        } finally {
            $this->pendingOverrides = [];
        }
    }

    public function allocate(User $user, int $transactionId, int $accountId, Money $amount, string $currency, CarbonInterface $date): void
    {
        if (! $amount->isPositive() || $this->alreadyAllocated($user, $transactionId)) {
            return;
        }

        $buckets = Bucket::forUser($user)->where('currency', $currency)->inPriorityOrder()->get();
        $overrides = array_intersect_key($this->overridesFor($user, $transactionId), $buckets->pluck('id', 'id')->all());

        if ($overrides === [] && $buckets->every(fn (Bucket $bucket) => $bucket->fill_rule === null)) {
            return;
        }

        $inputs = $buckets
            ->filter(fn (Bucket $bucket) => $bucket->acceptsAccount($accountId) && ! $bucket->isCompletedOn($date))
            ->map(fn (Bucket $bucket) => new WaterfallBucket(
                $bucket->id,
                $bucket->fill_rule,
                $this->funds->fillValueFor($bucket, $date),
                $this->room($bucket, $date),
                $this->funds->receivedInCycle($bucket, $date),
            ))
            ->values()
            ->all();

        $result = $this->waterfall->distribute($amount, $inputs, $overrides);
        $source = $overrides !== [] ? AllocationSource::Planned : AllocationSource::Income;

        foreach ($result['allocations'] as $bucketId => $money) {
            $this->funds->credit($buckets->firstWhere('id', $bucketId), $money, $source, $transactionId, $date);
        }

        if ($result['leftover']->isPositive()) {
            $default = $this->funds->ensureDefaultBucket($user, $currency);

            if ($default->acceptsAccount($accountId)) {
                $this->funds->credit($default, $result['leftover'], $source, $transactionId, $date);
            }
        }
    }

    /**
     * Releases an income and allocates it again (a plan was linked to it, so its explicit amounts now apply).
     */
    public function reallocate(User $user, int $transactionId, int $accountId, Money $amount, string $currency, CarbonInterface $date): void
    {
        DB::transaction(function () use ($user, $transactionId, $accountId, $amount, $currency, $date) {
            $this->release($user, $transactionId);
            $this->allocate($user, $transactionId, $accountId, $amount, $currency, $date);
        });
    }

    /**
     * Takes an income's money back out of its buckets. A bucket only gives back what it still has: when part of
     * the income was already moved, spent or carried away, the bucket is brought back to zero (not below) with a
     * move from the currency's default bucket, so the totals still match the real income and the shortfall shows
     * up in the default ("Unallocated") bucket, which may go negative.
     */
    public function release(User $user, int $transactionId): void
    {
        DB::transaction(function () use ($user, $transactionId) {
            $rows = Allocation::forUser($user)
                ->whereIn('source', [AllocationSource::Income->value, AllocationSource::Planned->value])
                ->where('source_id', $transactionId)
                ->get();

            if ($rows->isEmpty()) {
                return;
            }

            Allocation::forUser($user)->whereKey($rows->modelKeys())->delete();

            foreach ($rows->groupBy('bucket_id') as $bucketId => $bucketRows) {
                $bucket = Bucket::forUser($user)->find($bucketId);
                $released = $bucketRows->reduce(fn (Money $sum, Allocation $row) => $sum->add($row->amount), Money::zero());

                if ($bucket === null || $bucket->is_default || ! $released->isPositive()) {
                    continue;
                }

                $available = $this->funds->available($bucket);
                if (! $available->isNegative()) {
                    continue;
                }

                $shortfall = $available->abs()->lessThan($released) ? $available->abs() : $released;
                $this->funds->move(
                    $this->funds->ensureDefaultBucket($user, $bucket->currency),
                    $bucket,
                    $shortfall,
                    __('buckets.release_shortfall'),
                );
            }
        });
    }

    /**
     * Explicit per-bucket amounts for this income: the ones being realized right now, or those of the plan the
     * income realizes.
     *
     * @return array<int, Money>
     */
    private function overridesFor(User $user, int $transactionId): array
    {
        if ($this->pendingOverrides !== []) {
            return $this->pendingOverrides;
        }

        $planned = PlannedEvent::forUser($user)
            ->where('realized_transaction_id', $transactionId)
            ->where('type', 'income')
            ->first(['id', 'allocation_overrides']);

        return collect($planned?->allocation_overrides ?? [])
            ->mapWithKeys(fn (array $row) => [(int) $row['bucket_id'] => Money::of($row['amount'])])
            ->all();
    }

    private function alreadyAllocated(User $user, int $transactionId): bool
    {
        return Allocation::forUser($user)
            ->whereIn('source', [AllocationSource::Income->value, AllocationSource::Planned->value])
            ->where('source_id', $transactionId)
            ->exists();
    }

    /**
     * How much more the bucket can take: up to its target for savings buckets and
     * fill-to-target rules. Spending limits are not funding targets.
     */
    private function room(Bucket $bucket, CarbonInterface $date): ?Money
    {
        $cap = $bucket->effectiveCap($date);

        if ($cap === null) {
            return null;
        }

        if ($bucket->fill_rule !== FillRule::FillToTarget && $bucket->kind !== BucketKind::Savings) {
            return null;
        }

        $missing = $cap->subtract($this->funds->available($bucket));

        return $missing->isPositive() ? $missing : Money::zero();
    }
}
