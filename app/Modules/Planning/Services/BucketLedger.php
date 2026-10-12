<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The virtual money ledger: every change to a bucket's funded money is an
 * allocation row. Balances are always derived from these rows.
 */
class BucketLedger
{
    public function __construct(private readonly LedgerQuery $ledger) {}

    /**
     * Sum of all allocations (funded money).
     */
    public function balance(Bucket $bucket): Money
    {
        return Money::of(Allocation::forUser($bucket->user_id)->where('bucket_id', $bucket->id)->sum('amount'));
    }

    /**
     * Funded money still available to use. For a category-linked spending
     * bucket, real spending since tracking began is subtracted, so the
     * envelope shrinks as the category is spent.
     */
    public function available(Bucket $bucket): Money
    {
        $balance = $this->balance($bucket);

        if ($bucket->kind === BucketKind::Spending && $bucket->category_id !== null) {
            $spent = $this->ledger->expenseTotal($bucket->category_id, $bucket->currency, $bucket->tracking_started_on, today());
            $balance = $balance->subtract($spent);
        }

        return $balance;
    }

    /**
     * Whether the bucket has ever been funded or is set up to be.
     */
    public function hasFunding(Bucket $bucket): bool
    {
        return $bucket->fill_rule !== null
            || Allocation::forUser($bucket->user_id)->where('bucket_id', $bucket->id)->exists();
    }

    public function credit(
        Bucket $bucket,
        Money $amount,
        AllocationSource $source,
        ?int $sourceId = null,
        ?CarbonInterface $date = null,
        ?string $description = null,
    ): Allocation {
        $allocation = new Allocation([
            'bucket_id' => $bucket->id,
            'amount' => $amount,
            'currency' => $bucket->currency,
            'date' => ($date ?? today())->toDateString(),
            'source' => $source,
            'source_id' => $sourceId,
            'description' => $description,
        ]);
        $allocation->user_id = $bucket->user_id;
        $allocation->save();

        return $allocation;
    }

    /**
     * Moves funded money from one bucket to another of the same currency.
     */
    public function move(Bucket $from, Bucket $to, Money $amount, ?string $description = null): void
    {
        if (! $amount->isPositive() || $from->currency !== $to->currency || $from->is($to)) {
            throw new InvalidArgumentException('Money can only move between two different buckets of the same currency.');
        }

        DB::transaction(function () use ($from, $to, $amount, $description) {
            $this->credit($from, $amount->negate(), AllocationSource::Transfer, $to->id, null, $description);
            $this->credit($to, $amount, AllocationSource::Transfer, $from->id, null, $description);
        });
    }

    public function defaultBucket(User|int $user, string $currency): ?Bucket
    {
        return Bucket::forUser($user)->where('currency', $currency)->where('is_default', true)->first();
    }

    /**
     * The currency's catch-all bucket, created on first need.
     */
    public function ensureDefaultBucket(User $user, string $currency): Bucket
    {
        $existing = $this->defaultBucket($user, $currency);
        if ($existing !== null) {
            return $existing;
        }

        $bucket = new Bucket([
            'name' => __('buckets.default_name'),
            'kind' => BucketKind::Savings,
            'currency' => $currency,
            'priority' => 9999,
            'is_default' => true,
            'tracking_started_on' => Carbon::today()->toDateString(),
        ]);
        $bucket->user_id = $user->id;
        $bucket->save();

        return $bucket;
    }

    /**
     * Before a bucket is deleted its positive funded money goes to the default bucket.
     */
    public function releaseFunds(Bucket $bucket): void
    {
        $balance = $this->balance($bucket);
        $default = $this->defaultBucket($bucket->user_id, $bucket->currency);

        if ($balance->isPositive() && $default !== null && ! $default->is($bucket)) {
            $this->move($bucket, $default, $balance);
        }
    }
}
