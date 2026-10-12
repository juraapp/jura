<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Planning\Models\Budget;
use App\Modules\Shared\Support\Money;

interface BudgetProgress
{
    /**
     * @return array{spent: Money, remaining: Money, percentage: float, status: string}
     */
    public function progress(Budget $budget): array;
}
