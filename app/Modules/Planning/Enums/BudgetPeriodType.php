<?php

namespace App\Modules\Planning\Enums;

enum BudgetPeriodType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('enums.budget_period_type.'.$this->value);
    }
}
