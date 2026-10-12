<?php

namespace Tests\Unit;

use App\Modules\Planning\Support\YieldSimulator;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class YieldSimulatorTest extends TestCase
{
    public function test_daily_compounding_grows_the_balance_by_the_daily_rate(): void
    {
        $months = (new YieldSimulator)->run(
            Money::of(1000000), '9.3', 'daily', CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'),
        );

        $expected = 1000000 * ((1 + 0.093 / 365) ** 30) - 1000000;

        $this->assertEqualsWithDelta($expected, $months['2026-11']['interest']->toFloat(), 1);
        $this->assertEqualsWithDelta(1000000 + $expected, $months['2026-11']['closing']->toFloat(), 1);
    }

    public function test_monthly_compounding_adds_interest_only_at_month_end(): void
    {
        $months = (new YieldSimulator)->run(
            Money::of(1000000), '12', 'monthly', CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-12-31'),
        );

        $november = 1000000 * 0.12 / 365 * 30;
        $this->assertEqualsWithDelta($november, $months['2026-11']['interest']->toFloat(), 1);
        // December earns on the principal that already includes November's credited interest
        $this->assertGreaterThan(1000000 * 0.12 / 365 * 31, $months['2026-12']['interest']->toFloat());
    }

    public function test_money_moving_in_starts_earning_from_its_date(): void
    {
        $months = (new YieldSimulator)->run(
            Money::zero(), '36.5', 'daily', CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'),
            ['2026-11-21' => Money::of(1000000)],
        );

        // 10 days (21st to 30th) at 0.1% a day, compounded
        $this->assertEqualsWithDelta(1000000 * (1.001 ** 10) - 1000000, $months['2026-11']['interest']->toFloat(), 1);
    }
}
