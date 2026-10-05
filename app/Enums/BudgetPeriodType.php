<?php

namespace App\Enums;

enum BudgetPeriodType: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Yearly => __('Annual'),
        };
    }
}
