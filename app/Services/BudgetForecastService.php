<?php

namespace App\Services;

use App\Models\Budget;
use App\Support\Money;

class BudgetForecastService
{
    /**
     * @return array{projected: Money, allocated: Money, effectiveRemaining: Money}
     */
    public function summary(Budget $budget): array
    {
        $projected = Money::of(
            $budget->forecasts()
                ->where('is_anticipated', true)
                ->where('currency', $budget->currency)
                ->sum('amount')
        );

        $allocated = $budget->allocated_amount
            ? Money::of($budget->allocated_amount)
            : Money::zero();

        $effectiveRemaining = $budget->amount
            ->subtract($projected)
            ->add($allocated);

        return [
            'projected' => $projected,
            'allocated' => $allocated,
            'effectiveRemaining' => $effectiveRemaining,
        ];
    }
}
