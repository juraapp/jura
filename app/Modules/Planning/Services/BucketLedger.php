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
    public function balance(Bucket $bucket, ?CarbonInterface $asOf = null): Money
    {
        return Money::of(
            Allocation::forUser($bucket->user_id)
                ->where('bucket_id', $bucket->id)
                ->when($asOf !== null, fn ($query) => $query->where('date', '<=', $asOf->toDateString()))
                ->sum('amount')
        );
    }

    /**
     * Funded money still available to use. For a category-linked spending
     * bucket, real spending since tracking began is subtracted, so the
     * envelope shrinks as the category is spent.
     */
    public function available(Bucket $bucket, ?CarbonInterface $asOf = null): Money
    {
        $balance = $this->balance($bucket, $asOf);

        if ($bucket->kind === BucketKind::Spending && $bucket->category_id !== null) {
            $spent = $this->ledger->expenseTotal($bucket->category_id, $bucket->currency, $bucket->tracking_started_on, $asOf ?? today());
            $balance = $balance->subtract($spent);
        }

        return $balance;
    }

    /**
     * Income the bucket already received in the cycle containing the date, or null when it has no cycle
     * (a fixed rule then applies to every income).
     */
    public function receivedInCycle(Bucket $bucket, CarbonInterface $date): ?Money
    {
        $period = $bucket->cyclePeriod();

        if ($period === null) {
            return null;
        }

        $moment = $date->toDateTimeImmutable();

        return Money::of(
            Allocation::forUser($bucket->user_id)
                ->where('bucket_id', $bucket->id)
                ->whereIn('source', [AllocationSource::Income->value, AllocationSource::Planned->value])
                ->whereBetween('date', [$period->startOf($moment)->format('Y-m-d'), $period->endOf($moment)->format('Y-m-d')])
                ->sum('amount')
        );
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
    public function move(
        Bucket $from,
        Bucket $to,
        Money $amount,
        ?string $description = null,
        AllocationSource $source = AllocationSource::Transfer,
        ?CarbonInterface $date = null,
    ): void {
        if (! $amount->isPositive() || $from->currency !== $to->currency || $from->is($to)) {
            throw new InvalidArgumentException('Money can only move between two different buckets of the same currency.');
        }

        DB::transaction(function () use ($from, $to, $amount, $description, $source, $date) {
            $this->credit($from, $amount->negate(), $source, $to->id, $date, $description);
            $this->credit($to, $amount, $source, $from->id, $date, $description);
        });
    }

    public function openingBalance(Bucket $bucket): Money
    {
        return Money::of(
            Allocation::forUser($bucket->user_id)
                ->where('bucket_id', $bucket->id)
                ->where('source', AllocationSource::Opening->value)
                ->sum('amount')
        );
    }

    /**
     * The money a bucket started with: a single allocation dated when tracking began.
     * Zero removes it.
     */
    public function setOpeningBalance(Bucket $bucket, Money $amount): void
    {
        $existing = Allocation::forUser($bucket->user_id)
            ->where('bucket_id', $bucket->id)
            ->where('source', AllocationSource::Opening->value)
            ->first();

        if (! $amount->isPositive()) {
            $existing?->delete();

            return;
        }

        if ($existing !== null) {
            $existing->update(['amount' => $amount]);

            return;
        }

        $this->credit($bucket, $amount, AllocationSource::Opening, null, $bucket->tracking_started_on, __('buckets.opening_balance'));
    }

    /**
     * Whether a bucket's allocation is something the user entered (and may therefore remove).
     */
    public function isRemovable(Allocation $allocation): bool
    {
        return in_array($allocation->source, [AllocationSource::Adjustment, AllocationSource::Opening, AllocationSource::Transfer], true);
    }

    /**
     * Removes a manual allocation; a move between buckets is removed on both sides.
     */
    public function remove(Allocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            if ($allocation->source === AllocationSource::Transfer && $allocation->source_id !== null) {
                Allocation::forUser($allocation->user_id)
                    ->where('bucket_id', $allocation->source_id)
                    ->where('source', AllocationSource::Transfer->value)
                    ->where('source_id', $allocation->bucket_id)
                    ->where('amount', $allocation->amount->negate()->toDecimalString())
                    ->whereDate('date', $allocation->date->toDateString())
                    ->limit(1)
                    ->delete();
            }

            $allocation->delete();
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
