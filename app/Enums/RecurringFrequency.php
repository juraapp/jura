<?php

namespace App\Enums;

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
