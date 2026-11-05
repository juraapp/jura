<?php

namespace Tests\Unit;

use App\Modules\Planning\Support\Forecast\CashTimeline;
use App\Modules\Planning\Support\Forecast\EngineFlow;
use App\Modules\Planning\Support\Forecast\FlowKind;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CashTimelineTest extends TestCase
{
    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    private function timeline(): CashTimeline
    {
        return CashTimeline::build($this->day('2026-11-01'), Money::of(1000), [
            new EngineFlow($this->day('2026-10-20'), 0, FlowKind::Expense, Money::of(100)), // overdue: counts on the start date
            new EngineFlow($this->day('2026-11-05'), 1, FlowKind::Income, Money::of(500)),
            new EngineFlow($this->day('2026-11-05'), 2, FlowKind::Expense, Money::of(1200)),
            new EngineFlow($this->day('2026-11-20'), 3, FlowKind::Boundary, Money::of(-50)),
            new EngineFlow($this->day('2026-12-01'), 4, FlowKind::Income, Money::of(2000)),
        ]);
    }

    public function test_balances_are_end_of_day_and_hold_between_flows(): void
    {
        $timeline = $this->timeline();

        $this->assertSame('900.00', $timeline->at($this->day('2026-11-01'))->toDecimalString());
        $this->assertSame('900.00', $timeline->at($this->day('2026-11-04'))->toDecimalString());
        // 900 + 500 - 1200: the income and the expense on the 5th net out by the end of the day.
        $this->assertSame('200.00', $timeline->at($this->day('2026-11-05'))->toDecimalString());
        $this->assertSame('150.00', $timeline->at($this->day('2026-11-20'))->toDecimalString());
        $this->assertSame('2150.00', $timeline->at($this->day('2027-06-01'))->toDecimalString());
    }

    public function test_the_suffix_minimum_is_the_lowest_balance_from_a_day_on(): void
    {
        $timeline = $this->timeline();

        $this->assertSame('150.00', $timeline->suffixMin($this->day('2026-11-01'))->toDecimalString());
        $this->assertSame('150.00', $timeline->suffixMin($this->day('2026-11-06'))->toDecimalString());
        $this->assertSame('150.00', $timeline->suffixMin($this->day('2026-11-25'))->toDecimalString(), 'between flows the balance itself counts');
        $this->assertSame('2150.00', $timeline->suffixMin($this->day('2026-12-01'))->toDecimalString());
    }

    public function test_without_flows_cash_stays_at_the_start(): void
    {
        $timeline = CashTimeline::build($this->day('2026-11-01'), Money::of(300), []);

        $this->assertSame('300.00', $timeline->at($this->day('2027-01-01'))->toDecimalString());
        $this->assertSame('300.00', $timeline->suffixMin($this->day('2026-11-01'))->toDecimalString());
        $this->assertSame([], $timeline->points());
    }
}
