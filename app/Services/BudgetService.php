<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Carbon;

class BudgetService
{
    private const NEAR_LIMIT_THRESHOLD = 80.0;

    /**
     * @return array{spent: Money, remaining: Money, percentage: float, status: string}
     */
    public function progress(Budget $budget): array
    {
        [$start, $end] = $this->periodBounds($budget);

        $spent = Money::of(
            Transaction::query()
                ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                ->where('transactions.category_id', $budget->category_id)
                ->where('transactions.type', TransactionType::Expense->value)
                ->where('accounts.currency', $budget->currency)
                ->whereBetween('transactions.date', [$start, $end])
                ->sum('transactions.amount')
        );

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
