<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Support\Waterfall;
use App\Modules\Planning\Support\WaterfallBucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Closure;

/**
 * Splits a recorded income across the user's buckets. Runs automatically only
 * when at least one bucket has a fill rule, or when a planned income carries
 * explicit per-bucket amounts.
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
        $overrides = array_intersect_key($this->pendingOverrides, $buckets->pluck('id', 'id')->all());

        if ($overrides === [] && $buckets->every(fn (Bucket $bucket) => $bucket->fill_rule === null)) {
            return;
        }

        $inputs = $buckets
            ->filter(fn (Bucket $bucket) => $bucket->acceptsAccount($accountId))
            ->map(fn (Bucket $bucket) => new WaterfallBucket(
                $bucket->id,
                $bucket->fill_rule,
                $bucket->fill_value !== null ? (string) $bucket->fill_value : null,
                $this->room($bucket),
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

    public function release(User $user, int $transactionId): void
    {
        Allocation::forUser($user)
            ->whereIn('source', [AllocationSource::Income->value, AllocationSource::Planned->value])
            ->where('source_id', $transactionId)
            ->delete();
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
    private function room(Bucket $bucket): ?Money
    {
        if ($bucket->cap_amount === null) {
            return null;
        }

        if ($bucket->fill_rule !== FillRule::FillToTarget && $bucket->kind !== BucketKind::Savings) {
            return null;
        }

        $missing = $bucket->cap_amount->subtract($this->funds->available($bucket));

        return $missing->isPositive() ? $missing : Money::zero();
    }
}
