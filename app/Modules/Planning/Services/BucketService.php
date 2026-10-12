<?php

namespace App\Modules\Planning\Services;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Contracts\BucketProgress;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Limit tracking for spending buckets: how much of the per-cycle limit the
 * category's real spending has used.
 */
class BucketService implements BucketProgress
{
    private const NEAR_LIMIT_THRESHOLD = 80.0;

    public function __construct(private readonly LedgerQuery $ledger) {}

    /**
     * @return array{spent: Money, remaining: Money, percentage: float, status: string}
     */
    public function progress(Bucket $bucket): array
    {
        [$start, $end] = $this->periodBounds($bucket);

        $spent = $this->ledger->expenseTotal($bucket->category_id, $bucket->currency, $start, $end);

        $percentage = $spent->percentageOf($bucket->cap_amount);
        $remaining = $bucket->cap_amount->subtract($spent);

        $status = match (true) {
            $percentage >= 100 => 'exceeded',
            $percentage >= self::NEAR_LIMIT_THRESHOLD => 'near_limit',
            default => 'on_track',
        };

        return [
            'spent' => $spent,
            'remaining' => $remaining,
            'percentage' => min($percentage, 999),
            'status' => $status,
        ];
    }

    /**
     * The current cycle's first and last day (the month or year containing $at).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodBounds(Bucket $bucket, ?CarbonInterface $at = null): array
    {
        $at = Carbon::instance($at ?? now());

        return $bucket->cycle === BudgetPeriodType::Yearly
            ? [$at->copy()->startOfYear(), $at->copy()->endOfYear()]
            : [$at->copy()->startOfMonth(), $at->copy()->endOfMonth()];
    }
}
