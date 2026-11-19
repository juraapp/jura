<?php

namespace App\Modules\Planning\Services;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\BucketCycle;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Closes finished cycles, oldest first (so a stopped scheduler or a backdated bucket
 * catches up on its own). Each closed cycle is stored as a snapshot, and whatever the
 * bucket still had follows its carry-over policy.
 */
class BucketCycleService
{
    private const INCOME_SOURCES = [AllocationSource::Income, AllocationSource::Planned];

    public function __construct(
        private readonly BucketLedger $funds,
        private readonly LedgerQuery $ledger,
    ) {}

    /**
     * Closes every finished cycle. Without a date, "today" is each bucket owner's own date in their timezone,
     * so a cycle closes once it has ended where the user lives (the command runs hourly).
     *
     * @return int how many cycles were closed
     */
    public function closeDue(?CarbonInterface $today = null): int
    {
        $fixedToday = $today !== null ? Carbon::instance($today)->startOfDay() : null;
        $closed = 0;

        Bucket::withoutGlobalScope('user')->with('user')->whereNotNull('cycle')->chunkById(100, function ($batch) use ($fixedToday, &$closed) {
            foreach ($batch as $bucket) {
                if ($bucket->user === null) {
                    continue;
                }

                $closed += $this->closeBucket($bucket, $fixedToday ?? Carbon::instance($bucket->user->today()));
            }
        });

        return $closed;
    }

    public function closeBucket(Bucket $bucket, Carbon $today): int
    {
        $period = $bucket->cyclePeriod();
        if ($period === null) {
            return 0;
        }

        $from = $bucket->cycle_closed_through?->copy()->addDay() ?? $bucket->tracking_started_on;
        $closed = 0;

        foreach ($period->completedBetween($from->toDateTimeImmutable(), $today->toDateTimeImmutable()) as [$start, $end]) {
            if ($bucket->cycle_closed_through !== null && $end <= $bucket->cycle_closed_through->toDateTimeImmutable()) {
                continue;
            }

            $this->closeCycle($bucket, Carbon::instance($start), Carbon::instance($end));
            $closed++;
        }

        return $closed;
    }

    /**
     * The limit that applies on a date: a limit set for that cycle only, else the bucket's own.
     */
    public function capFor(Bucket $bucket, CarbonInterface $date): ?Money
    {
        return $bucket->effectiveCap($date);
    }

    private function closeCycle(Bucket $bucket, Carbon $start, Carbon $end): void
    {
        DB::transaction(function () use ($bucket, $start, $end) {
            $figures = $this->figures($bucket, $start, $end);
            $leftover = $this->funds->available($bucket, $end);
            $released = $this->applyCarryOver($bucket, $end, $leftover);

            $existing = BucketCycle::forUser($bucket->user_id)->where('bucket_id', $bucket->id)->where('starts_on', $start->toDateString())->first();
            $override = $existing?->cap_override;

            $cycle = $existing ?? new BucketCycle(['bucket_id' => $bucket->id, 'starts_on' => $start->toDateString()]);
            $cycle->user_id = $bucket->user_id;
            $cycle->fill($figures + [
                'ends_on' => $end->toDateString(),
                'status' => BucketCycle::CLOSED,
                'released' => $released,
                'closing' => $leftover->subtract($released),
                'cap' => $bucket->kind === BucketKind::Savings ? $bucket->cycle_goal : ($override ?? $bucket->cap_amount),
                'closed_at' => now(),
            ])->save();

            $bucket->update(['cycle_closed_through' => $end->toDateString()]);
        });
    }

    /**
     * How the bucket moved during one cycle: opening + filled + moved in - moved out - spent = leftover. Spent is
     * the category's spending plus purchases paid from the bucket; moves to and from other buckets and ready to
     * assign count as moved.
     *
     * @return array{opening: Money, filled: Money, moved_in: Money, moved_out: Money, spent: Money}
     */
    public function figures(Bucket $bucket, CarbonInterface $start, CarbonInterface $end): array
    {
        $filled = Money::zero();
        $in = Money::zero();
        $out = Money::zero();
        $spentFromBucket = Money::zero();

        Allocation::forUser($bucket->user_id)
            ->where('bucket_id', $bucket->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (Allocation $allocation) use (&$filled, &$in, &$out, &$spentFromBucket) {
                if (in_array($allocation->source, self::INCOME_SOURCES, true)) {
                    $filled = $filled->add($allocation->amount);
                } elseif ($allocation->source === AllocationSource::Spend) {
                    $spentFromBucket = $spentFromBucket->subtract($allocation->amount);
                } elseif ($allocation->amount->isPositive()) {
                    $in = $in->add($allocation->amount);
                } else {
                    $out = $out->add($allocation->amount->abs());
                }
            });

        $spent = $spentFromBucket;
        if ($bucket->kind === BucketKind::Spending && $bucket->category_id !== null) {
            $since = $this->funds->fundedSince($bucket) ?? $bucket->tracking_started_on;
            $from = $start->lt($since) ? $since : $start;
            $spent = $from->gt($end) ? $spent : $spent->add($this->ledger->expenseTotal($bucket->user_id, $bucket->category_id, $bucket->currency, $from, $end, onBudgetOnly: true));
        }

        return [
            'opening' => $this->funds->available($bucket, $start->copy()->subDay()),
            'filled' => $filled,
            'moved_in' => $in,
            'moved_out' => $out,
            'spent' => $spent,
        ];
    }

    /**
     * @return Money what moved out of the bucket
     */
    private function applyCarryOver(Bucket $bucket, Carbon $end, Money $leftover): Money
    {
        if ($bucket->carry_over === CarryOverPolicy::Carry || ! $leftover->isPositive()) {
            return Money::zero();
        }

        $date = $end->copy()->addDay();
        $target = $bucket->carry_over === CarryOverPolicy::Overflow ? $bucket->overflowBucket : null;

        if ($target === null) {
            $this->funds->releaseToReady($bucket, $leftover, $date);

            return $leftover;
        }

        if ($target->is($bucket)) {
            return Money::zero();
        }

        $this->funds->move($bucket, $target, $leftover, null, AllocationSource::Carry, $date);

        return $leftover;
    }
}
