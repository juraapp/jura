<?php

namespace App\Modules\Planning\Services;

use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Models\Bucket;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Closes finished cycles: whatever a bucket had left at the end of its last
 * completed month or year follows its carry-over policy.
 */
class BucketCycleService
{
    public function __construct(private readonly BucketLedger $funds) {}

    /**
     * @return int how many buckets had a cycle closed
     */
    public function closeDue(?CarbonInterface $today = null): int
    {
        $today = Carbon::instance($today ?? now())->startOfDay();
        $closed = 0;

        Bucket::withoutGlobalScope('user')->whereNotNull('cycle')->chunkById(100, function ($batch) use ($today, &$closed) {
            foreach ($batch as $bucket) {
                if ($this->closeBucket($bucket, $today)) {
                    $closed++;
                }
            }
        });

        return $closed;
    }

    private function closeBucket(Bucket $bucket, Carbon $today): bool
    {
        $end = $this->lastCompletedCycleEnd($bucket, $today);

        if ($bucket->tracking_started_on->gt($end)) {
            return false;
        }

        if ($bucket->cycle_closed_through !== null && $bucket->cycle_closed_through->gte($end)) {
            return false;
        }

        DB::transaction(function () use ($bucket, $end) {
            $this->applyCarryOver($bucket, $end);
            $bucket->update(['cycle_closed_through' => $end->toDateString()]);
        });

        return true;
    }

    private function applyCarryOver(Bucket $bucket, Carbon $end): void
    {
        if ($bucket->carry_over === CarryOverPolicy::Carry) {
            return;
        }

        $leftover = $this->funds->available($bucket, $end);
        if (! $leftover->isPositive()) {
            return;
        }

        $target = $bucket->carry_over === CarryOverPolicy::Overflow ? $bucket->overflowBucket : null;
        $target ??= $this->funds->ensureDefaultBucket($bucket->user, $bucket->currency);

        if ($target->is($bucket)) {
            return;
        }

        $this->funds->move($bucket, $target, $leftover, null, AllocationSource::Carry, $end->copy()->addDay());
    }

    private function lastCompletedCycleEnd(Bucket $bucket, Carbon $today): Carbon
    {
        return $bucket->cycle === BudgetPeriodType::Yearly
            ? $today->copy()->subYear()->endOfYear()->startOfDay()
            : $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay();
    }
}
