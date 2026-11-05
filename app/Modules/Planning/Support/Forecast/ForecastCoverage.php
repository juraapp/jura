<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;

/**
 * Which planned incomes a forecast counts: the ones the view trusts out of every open planned income into the
 * budget, how many of the counted ones are overdue (they count as today), and incomes planned into off-budget
 * accounts, which never count toward ready to assign.
 */
final readonly class ForecastCoverage
{
    public function __construct(
        public string $view,
        public int $incomesCounted,
        public Money $countedAmount,
        public int $incomesTotal,
        public Money $totalAmount,
        public int $overdueCounted,
        public int $offBudgetIncomes,
        public Money $offBudgetAmount,
    ) {}
}
