<?php

namespace Tests\Unit;

use App\Modules\Planning\Support\SavingsPace;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SavingsPaceTest extends TestCase
{
    /**
     * @return array{expectedPercent: float, percent: float, behind: bool, neededPerMonth: string}
     */
    private function paceOf(int|string $saved, string $today, string $started = '2026-01-01', string $target = '2026-01-11', int $targetAmount = 1000): array
    {
        $status = (new SavingsPace)->status(Money::of($targetAmount), Money::of($saved), CarbonImmutable::parse($started), CarbonImmutable::parse($target), CarbonImmutable::parse($today));
        $status['neededPerMonth'] = $status['neededPerMonth']->toDecimalString();

        return $status;
    }

    public function test_half_way_in_time_and_money_is_on_pace(): void
    {
        // Ten days from Jan 1 to Jan 11; five have passed: 50% expected, 500 of 1,000 saved is 50%.
        $status = $this->paceOf(500, '2026-01-06');

        $this->assertSame([50.0, 50.0, false], [$status['expectedPercent'], $status['percent'], $status['behind']]);
    }

    public function test_two_points_of_slack_are_allowed(): void
    {
        // 480 is 48% + 2 = 50: still on pace. 479 is 47.9% + 2 = 49.9 < 50: behind.
        $this->assertFalse($this->paceOf(480, '2026-01-06')['behind']);
        $this->assertTrue($this->paceOf(479, '2026-01-06')['behind']);
    }

    public function test_a_finished_target_is_never_behind_and_needs_nothing(): void
    {
        $status = $this->paceOf(1000, '2026-01-10');

        $this->assertSame([false, '0.00'], [$status['behind'], $status['neededPerMonth']]);
    }

    public function test_before_saving_starts_nothing_is_expected(): void
    {
        $status = $this->paceOf(0, '2025-12-20');

        $this->assertSame([0.0, false], [$status['expectedPercent'], $status['behind']]);
    }

    public function test_after_the_target_date_everything_was_expected_and_nothing_is_left_to_spread(): void
    {
        $status = $this->paceOf(600, '2026-02-01');

        $this->assertSame([100.0, 60.0, true, '0.00'], [$status['expectedPercent'], $status['percent'], $status['behind'], $status['neededPerMonth']]);
    }

    public function test_the_monthly_amount_spreads_what_is_missing_over_the_months_left(): void
    {
        // From Oct 12 to Jan 12 are three months (Nov 12, Dec 12, Jan 12): 300 missing is 100 a month.
        $this->assertSame('100.00', $this->paceOf(700, '2026-10-12', '2026-09-01', '2027-01-12')['neededPerMonth']);
        // To Jan 20 a fourth, started, month counts: 300 / 4 = 75 a month.
        $this->assertSame('75.00', $this->paceOf(700, '2026-10-12', '2026-09-01', '2027-01-20')['neededPerMonth']);
    }

    public function test_a_target_that_is_zero_is_complete(): void
    {
        $status = (new SavingsPace)->status(Money::zero(), Money::zero(), CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-11'), CarbonImmutable::parse('2026-01-06'));

        $this->assertSame([100.0, false], [$status['percent'], $status['behind']]);
    }
}
