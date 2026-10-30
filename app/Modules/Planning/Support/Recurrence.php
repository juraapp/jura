<?php

namespace App\Modules\Planning\Support;

use App\Modules\Planning\Enums\RecurringFrequency;
use Carbon\CarbonImmutable;

/**
 * The single source of a repeating plan's dates. Every occurrence is computed from the start date
 * (never by chaining), so month-end dates do not drift: Jan 31 -> Feb 28 -> Mar 31. Pure, no framework.
 */
final class Recurrence
{
    /** Safety bound on how many steps one call walks through (a weekly series spans about 190 years). */
    private const MAX_STEPS = 10000;

    /**
     * The occurrence dates of a rule, in order.
     *
     * @param  list<string>  $excluded  Y-m-d dates that were deleted and must never come back
     * @param  CarbonImmutable|null  $after  only dates strictly after this one (the last stored occurrence)
     * @param  CarbonImmutable|null  $from  only dates on or after this one (the owner's today)
     * @param  CarbonImmutable  $until  no date after this one (the horizon); the end date also applies
     * @return list<CarbonImmutable>
     */
    public static function occurrences(
        RecurringFrequency $frequency,
        CarbonImmutable $start,
        ?CarbonImmutable $endsOn,
        array $excluded,
        ?CarbonImmutable $after,
        ?CarbonImmutable $from,
        CarbonImmutable $until,
        int $max,
    ): array {
        $start = $start->startOfDay();
        $limit = $until->startOfDay();
        if ($endsOn !== null && $endsOn->startOfDay()->lt($limit)) {
            $limit = $endsOn->startOfDay();
        }

        $after = $after?->startOfDay();
        $from = $from?->startOfDay();
        $excluded = array_flip($excluded);

        $dates = [];
        for ($step = 0; $step < self::MAX_STEPS && count($dates) < $max; $step++) {
            $date = self::nth($frequency, $start, $step);
            if ($date->gt($limit)) {
                break;
            }

            if (($after !== null && $date->lte($after))
                || ($from !== null && $date->lt($from))
                || isset($excluded[$date->toDateString()])) {
                continue;
            }

            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * The date of the Nth occurrence after a start date.
     */
    public static function nth(RecurringFrequency $frequency, CarbonImmutable $start, int $steps): CarbonImmutable
    {
        return match ($frequency) {
            RecurringFrequency::Weekly => $start->addWeeks($steps),
            RecurringFrequency::Biweekly => $start->addWeeks(2 * $steps),
            RecurringFrequency::Monthly => $start->addMonthsNoOverflow($steps),
            RecurringFrequency::Yearly => $start->addYearsNoOverflow($steps),
        };
    }
}
