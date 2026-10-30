<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\CarryOverPolicy;
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
     * Funded money still available to use. For a category-linked spending bucket, real spending in on-budget
     * accounts since the bucket was funded is subtracted, so the envelope shrinks as the category is spent.
     * Without a date every recorded allocation and expense counts (future-dated ones included, the same cut-off
     * as account balances); with one, only those up to that date.
     */
    public function available(Bucket $bucket, ?CarbonInterface $asOf = null): Money
    {
        $balance = $this->balance($bucket, $asOf);

        if ($bucket->kind === BucketKind::Spending && $bucket->category_id !== null) {
            $from = $this->fundedSince($bucket) ?? $bucket->tracking_started_on;
            $spent = $asOf !== null && $from->gt($asOf)
                ? Money::zero()
                : $this->ledger->expenseTotal($bucket->user_id, $bucket->category_id, $bucket->currency, $from, $asOf, onBudgetOnly: true);
            $balance = $balance->subtract($spent);
        }

        return $balance;
    }

    /**
     * The bucket's envelope: what it holds right now, every recorded movement counted. Only funded buckets
     * have one in the money map; a limit-only spending bucket is a spending limit, not earmarked money.
     */
    public function envelope(Bucket $bucket): Money
    {
        return $this->available($bucket);
    }

    /**
     * Whether money was ever put in the bucket (at least one allocation).
     */
    public function isFunded(Bucket $bucket): bool
    {
        return Allocation::forUser($bucket->user_id)->where('bucket_id', $bucket->id)->exists();
    }

    /**
     * From when the bucket's category spending comes out of its envelope: tracking start or its first
     * allocation, whichever is later. Null while the bucket has never been funded.
     */
    public function fundedSince(Bucket $bucket): ?Carbon
    {
        $first = Allocation::forUser($bucket->user_id)->where('bucket_id', $bucket->id)->min('date');

        if ($first === null) {
            return null;
        }

        $first = Carbon::parse($first)->startOfDay();

        return $bucket->tracking_started_on !== null && $bucket->tracking_started_on->gt($first)
            ? $bucket->tracking_started_on->copy()
            : $first;
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
     * The fill value that applies on a date: the step-up value once the bucket it waits for has reached
     * its target, counted from the start of the current cycle ("from the following month onward").
     */
    public function fillValueFor(Bucket $bucket, CarbonInterface $date): ?string
    {
        $value = $bucket->fill_value !== null ? (string) $bucket->fill_value : null;

        if ($bucket->step_up_value === null || $bucket->step_up_after_bucket_id === null) {
            return $value;
        }

        $trigger = $bucket->stepUpAfter;
        if ($trigger === null) {
            return $value;
        }

        $period = $bucket->cyclePeriod();
        $cutoff = $period !== null
            ? Carbon::instance($period->startOf($date->toDateTimeImmutable()))->subDay()
            : Carbon::instance($date)->startOfDay();

        $reached = $trigger->completed_on !== null && $trigger->completed_on->lte($cutoff)
            || ($trigger->cap_amount !== null && ! $this->balance($trigger, $cutoff)->lessThan($trigger->cap_amount));

        return $reached ? (string) $bucket->step_up_value : $value;
    }

    /**
     * Closes a bucket whose goal is done: what is left in it moves to its overflow bucket (or back to ready to
     * assign) and it stops receiving income.
     */
    public function complete(Bucket $bucket, CarbonInterface $date): void
    {
        DB::transaction(function () use ($bucket, $date) {
            $left = $this->available($bucket);

            if ($left->isPositive()) {
                $target = $bucket->carry_over === CarryOverPolicy::Overflow && $bucket->overflow_bucket_id !== null
                    ? Bucket::forUser($bucket->user_id)->find($bucket->overflow_bucket_id)
                    : null;

                if ($target !== null && ! $target->is($bucket)) {
                    $this->move($bucket, $target, $left, null, AllocationSource::Carry, $date);
                } else {
                    $this->releaseToReady($bucket, $left, $date);
                }
            }

            $bucket->update(['completed_on' => $date->toDateString()]);
        });
    }

    /**
     * Gives money from a bucket back to ready to assign: a single negative `ready` row.
     */
    public function releaseToReady(Bucket $bucket, Money $amount, CarbonInterface $date, ?string $description = null): Allocation
    {
        return $this->credit($bucket, $amount->negate(), AllocationSource::Ready, null, $date, $description);
    }

    public function reopen(Bucket $bucket): void
    {
        $bucket->update(['completed_on' => null]);
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
            'date' => ($date ?? User::todayFor($bucket->user_id))->toDateString(),
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
}
