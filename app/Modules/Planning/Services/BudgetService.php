<?php

namespace App\Modules\Planning\Services;

use App\Modules\Planning\Models\Budget;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Contracts\BudgetProgress;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Carbon;

class BudgetService implements BudgetProgress
{
    private const NEAR_LIMIT_THRESHOLD = 80.0;

    public function __construct(private readonly LedgerQuery $ledger) {}

    /**
     * @return array{spent: Money, remaining: Money, percentage: float, status: string}
     */
    public function progress(Budget $budget): array
    {
        [$start, $end] = $this->periodBounds($budget);

        $spent = $this->ledger->expenseTotal($budget->category_id, $budget->currency, $start, $end);

        $percentage = $spent->percentageOf($budget->amount);
        $remaining = $budget->amount->subtract($spent);

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
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodBounds(Budget $budget): array
    {
        return $budget->period_type->value === 'yearly'
            ? [$budget->period_start->copy()->startOfYear(), $budget->period_start->copy()->endOfYear()]
            : [$budget->period_start->copy()->startOfMonth(), $budget->period_start->copy()->endOfMonth()];
    }
}
