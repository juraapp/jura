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

    public function addTo(Carbon $date): Carbon
    {
        return match ($this) {
            self::Weekly => $date->copy()->addWeek(),
            self::Biweekly => $date->copy()->addWeeks(2),
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Yearly => $date->copy()->addYearNoOverflow(),
        };
    }

    /**
     * The date of the Nth occurrence after a start date, computed from the start
     * (not by chaining) so month-end dates do not drift (Jan 31 -> Feb 28 -> Mar 31).
     */
    public function occurrence(Carbon $start, int $steps): Carbon
    {
        return match ($this) {
            self::Weekly => $start->copy()->addWeeks($steps),
            self::Biweekly => $start->copy()->addWeeks(2 * $steps),
            self::Monthly => $start->copy()->addMonthsNoOverflow($steps),
            self::Yearly => $start->copy()->addYearsNoOverflow($steps),
        };
    }
}
