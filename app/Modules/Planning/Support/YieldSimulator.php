<?php

namespace App\Modules\Planning\Support;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Projects the interest an account earns day by day: interest accrues daily at rate/365 on the
 * balance, and is added to that balance daily, monthly (last day of the month) or yearly (31 December).
 * Pure: no I/O. Projected data only; no transaction is ever created from it.
 */
final class YieldSimulator
{
    private const SCALE = 10;

    /**
     * @param  string  $annualRate  percent per year, e.g. "9.3"
     * @param  string  $compounding  daily|monthly|yearly
     * @param  array<string, Money>  $flows  net money moving in (+) or out (-) of the account by date (Y-m-d)
     * @return array<string, array{interest: Money, closing: Money}> by month (Y-m)
     */
    public function run(Money $opening, string $annualRate, string $compounding, CarbonImmutable $start, CarbonImmutable $end, array $flows = []): array
    {
        $principal = $opening->toDecimalString();
        $accrued = '0';
        $dailyRate = bcdiv(bcdiv($annualRate, '100', self::SCALE), '365', self::SCALE);
        $months = [];

        for ($day = $start->startOfDay(); $day->lte($end); $day = $day->addDay()) {
            $month = $day->format('Y-m');
            $months[$month] ??= ['interest' => '0'];

            if (isset($flows[$day->toDateString()])) {
                $principal = bcadd($principal, $flows[$day->toDateString()]->toDecimalString(), self::SCALE);
            }

            $earned = bccomp($principal, '0', self::SCALE) > 0 ? bcmul($principal, $dailyRate, self::SCALE) : '0';
            $accrued = bcadd($accrued, $earned, self::SCALE);
            $months[$month]['interest'] = bcadd($months[$month]['interest'], $earned, self::SCALE);

            if ($this->creditsOn($compounding, $day)) {
                $principal = bcadd($principal, $accrued, self::SCALE);
                $accrued = '0';
            }

            $months[$month]['closing'] = bcadd($principal, $accrued, self::SCALE);
        }

        return array_map(fn (array $row) => [
            'interest' => Money::of($row['interest']),
            'closing' => Money::of($row['closing']),
        ], $months);
    }

    private function creditsOn(string $compounding, CarbonImmutable $day): bool
    {
        return match ($compounding) {
            'monthly' => $day->isSameDay($day->endOfMonth()),
            'yearly' => $day->month === 12 && $day->day === 31,
            default => true,
        };
    }
}
