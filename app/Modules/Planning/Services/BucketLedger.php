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
     * Whether the bucket is an envelope in the money map: it was funded at some point (see fundedSince).
     */
    public function isFunded(Bucket $bucket): bool
    {
        return $this->storedFundedSince($bucket) !== null;
    }

    /**
     * From when the bucket's category spending comes out of its envelope: tracking start or the day it was first
     * funded, whichever is later. Null while the bucket has never been funded.
     *
     * The first-funded day is stored (`buckets.funded_since`): an allocation dated earlier moves it earlier, but
     * removing or redating the earliest allocation never moves it later, so spending recorded against the envelope
     * stays counted. It is cleared only when the bucket is left with no allocations and no category spending
     * since then (see forgetFundingIfUnused).
     */
    public function fundedSince(Bucket $bucket): ?Carbon
    {
        return $this->effectiveFrom($bucket, $this->storedFundedSince($bucket));
    }

    /**
     * Forgets that the bucket was funded once it holds no allocations and its category had no spending since it
     * was funded: nothing happened to that envelope, so it goes back to being a limit only (later spending then
     * comes out of ready to assign, not out of an empty envelope). With spending since, the date stays: that
     * spending was budgeted from the envelope, which now shows as overspent until it is covered.
     */
    public function forgetFundingIfUnused(Bucket $bucket): void
    {
        $since = $this->fundedSince($bucket);

        if ($since === null || Allocation::forUser($bucket->user_id)->where('bucket_id', $bucket->id)->exists()) {
            return;
        }

        if ($bucket->kind === BucketKind::Spending && $bucket->category_id !== null
            && ! $this->ledger->expenseTotal($bucket->user_id, $bucket->category_id, $bucket->currency, $since, null, onBudgetOnly: true)->isZero()) {
            return;
        }

        Bucket::forUser($bucket->user_id)->whereKey($bucket->id)->update(['funded_since' => null]);
        $bucket->funded_since = null;
    }

    /**
     * How much putting this much in the bucket on a date really moves out of ready to assign. Usually the amount
     * itself, but funding a category bucket earlier than before (or for the first time) also brings the category's
     * spending since that day into the envelope, which then holds less.
     */
    public function assignEffect(Bucket $bucket, Money $amount, ?CarbonInterface $date = null): Money
    {
        if (! $amount->isPositive() || $bucket->kind !== BucketKind::Spending || $bucket->category_id === null) {
            return $amount;
        }

        $date = Carbon::instance($date ?? User::todayFor($bucket->user_id))->startOfDay();
        $stored = $bucket->exists ? $this->storedFundedSince($bucket) : null;

        if ($stored !== null && $date->gte($stored)) {
            return $amount;
        }

        $newFrom = $this->effectiveFrom($bucket, $date);
        $oldFrom = $this->effectiveFrom($bucket, $stored);

        if ($oldFrom !== null && $newFrom->gte($oldFrom)) {
            return $amount;
        }

        return $amount->subtract($this->ledger->expenseTotal(
            $bucket->user_id, $bucket->category_id, $bucket->currency, $newFrom, $oldFrom?->copy()->subDay(), onBudgetOnly: true,
        ));
    }

    private function storedFundedSince(Bucket $bucket): ?Carbon
    {
        $stored = Bucket::forUser($bucket->user_id)->whereKey($bucket->id)->value('funded_since');

        return $stored === null ? null : Carbon::parse($stored)->startOfDay();
    }

    /**
     * The later of tracking start and the given first-funded day.
     */
    private function effectiveFrom(Bucket $bucket, ?CarbonInterface $funded): ?Carbon
    {
        if ($funded === null) {
            return null;
        }

        $funded = Carbon::instance($funded)->startOfDay();

        return $bucket->tracking_started_on !== null && $bucket->tracking_started_on->gt($funded)
            ? $bucket->tracking_started_on->copy()->startOfDay()
            : $funded;
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
        return $bucket->fill_rule !== null || $this->isFunded($bucket);
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
     * The money a bucket started with: a single allocation, dated the day it is first set (the user's today) so
     * that assigning it moves exactly that much out of ready to assign (only spending from that day on comes out
     * of it). Changing it later keeps that date; zero removes it.
     */
    public function setOpeningBalance(Bucket $bucket, Money $amount): void
    {
        $existing = Allocation::forUser($bucket->user_id)
            ->where('bucket_id', $bucket->id)
            ->where('source', AllocationSource::Opening->value)
            ->first();

        if (! $amount->isPositive()) {
            if ($existing !== null) {
                $existing->delete();
                $this->forgetFundingIfUnused($bucket);
            }

            return;
        }

        if ($existing !== null) {
            $existing->update(['amount' => $amount]);

            return;
        }

        $this->credit($bucket, $amount, AllocationSource::Opening, null, null, __('buckets.opening_balance'));
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
        $counterpart = $this->counterpart($allocation);

        DB::transaction(function () use ($allocation, $counterpart) {
            $counterpart?->delete();
            $allocation->delete();
        });

        $buckets = Bucket::forUser($allocation->user_id)->whereKey(array_filter([$allocation->bucket_id, $counterpart?->bucket_id]))->get();
        $buckets->each(fn (Bucket $bucket) => $this->forgetFundingIfUnused($bucket));
    }

    /**
     * How much removing a manual allocation puts back into buckets from ready to assign (negative when it gives
     * money back): a move between two buckets nets out, a lone negative row (a move to ready to assign, or one
     * whose other side is gone) takes its amount from ready to assign again.
     */
    public function removalEffect(Allocation $allocation): Money
    {
        return $allocation->amount->add($this->counterpart($allocation)?->amount ?? Money::zero())->negate();
    }

    /**
     * The other side of a move between buckets, if it still exists.
     */
    private function counterpart(Allocation $allocation): ?Allocation
    {
        if ($allocation->source !== AllocationSource::Transfer || $allocation->source_id === null) {
            return null;
        }

        return Allocation::forUser($allocation->user_id)
            ->where('bucket_id', $allocation->source_id)
            ->where('source', AllocationSource::Transfer->value)
            ->where('source_id', $allocation->bucket_id)
            ->where('amount', $allocation->amount->negate()->toDecimalString())
            ->whereDate('date', $allocation->date->toDateString())
            ->first();
    }
}
