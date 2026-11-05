<?php

namespace App\Modules\Planning\Enums;

use Illuminate\Support\Carbon;

enum RecurringFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('enums.recurring_frequency.'.$this->value);
    }

    /**
     * Half of one period in days: how far from its rule date a moved occurrence still stands for that date.
     */
    public function halfPeriodDays(): int
    {
        return match ($this) {
            self::Weekly => 3,
            self::Biweekly => 7,
            self::Monthly => 15,
            self::Yearly => 182,
        };
    }

    public function addTo(Carbon $date): Carbon
    {
        return match ($this) {
            self::Weekly => $date->copy()->addWeek(),
            self::Biweekly => $date->copy()->addWeeks(2),
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Yearly => $date->copy()->addYearNoOverflow(),
        };
    }
}
