<?php

namespace App\Modules\Planning\Support;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Whether a savings target is on pace for its date, assuming the money is put aside evenly from the day saving
 * started to the target date. Behind means more than TOLERANCE percentage points under that line. Pure: no I/O.
 */
final class SavingsPace
{
    /** Percentage points of slack before a target counts as behind (a small gap is not worth an alert). */
    public const TOLERANCE = 2.0;

    /**
     * @return array{expectedPercent: float, percent: float, behind: bool, neededPerMonth: Money}
     */
    public function status(Money $target, Money $saved, CarbonImmutable $startedOn, CarbonImmutable $targetDate, CarbonImmutable $today): array
    {
        $percent = $target->isPositive() ? max(0.0, $saved->percentageOf($target)) : 100.0;

        $totalDays = max(1, $startedOn->diffInDays($targetDate));
        $elapsedDays = min($totalDays, max(0, $startedOn->diffInDays($today, false)));
        $expectedPercent = $elapsedDays / $totalDays * 100;

        $missing = $target->subtract($saved);
        $done = ! $missing->isPositive();

        return [
            'expectedPercent' => $expectedPercent,
            'percent' => $percent,
            'behind' => ! $done && $percent + self::TOLERANCE < $expectedPercent,
            'neededPerMonth' => $done || $targetDate->lte($today) ? Money::zero() : $this->perMonth($missing, $today, $targetDate),
        ];
    }

    /**
     * What is missing spread over the months from today to the target date (at least one, counting a started month).
     */
    private function perMonth(Money $missing, CarbonImmutable $today, CarbonImmutable $targetDate): Money
    {
        $months = 0;
        for ($day = $today; $day->lt($targetDate); $day = $day->addMonthNoOverflow()) {
            $months++;
        }

        return $missing->divide(max(1, $months));
    }
}
