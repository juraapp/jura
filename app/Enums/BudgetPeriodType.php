<?php

namespace App\Enums;

enum BudgetPeriodType: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('enums.budget_period_type.'.$this->value);
    }
}
