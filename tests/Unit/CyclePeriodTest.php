<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Support\CyclePeriod;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CyclePeriodTest extends TestCase
{
    private function d(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }

    private function bounds(CyclePeriod $cycle, string $date): string
    {
        return $cycle->startOf($this->d($date))->format('Y-m-d').'..'.$cycle->endOf($this->d($date))->format('Y-m-d');
    }

    public function test_a_default_monthly_cycle_is_the_calendar_month(): void
    {
        $cycle = new CyclePeriod(BudgetPeriodType::Monthly);

        $this->assertSame('2026-10-01..2026-10-31', $this->bounds($cycle, '2026-10-12'));
        $this->assertSame('2026-02-01..2026-02-28', $this->bounds($cycle, '2026-02-28'));
        $this->assertSame('2024-02-01..2024-02-29', $this->bounds($cycle, '2024-02-10'));
    }

    public function test_a_monthly_cycle_can_start_on_another_day(): void
    {
        $cycle = new CyclePeriod(BudgetPeriodType::Monthly, 1, $this->d('2026-01-15'));

        $this->assertSame('2026-10-15..2026-11-14', $this->bounds($cycle, '2026-10-15'));
        $this->assertSame('2026-09-15..2026-10-14', $this->bounds($cycle, '2026-10-14'));
        $this->assertSame('2026-12-15..2027-01-14', $this->bounds($cycle, '2027-01-01'));
    }

    public function test_a_start_day_past_the_end_of_a_short_month_falls_on_its_last_day(): void
    {
        $cycle = new CyclePeriod(BudgetPeriodType::Monthly, 1, $this->d('2026-01-31'));

        $this->assertSame('2026-02-28..2026-03-30', $this->bounds($cycle, '2026-03-01'));
        $this->assertSame('2026-03-31..2026-04-29', $this->bounds($cycle, '2026-04-10'));
    }

    public function test_a_multi_month_cycle(): void
    {
        $quarter = new CyclePeriod(BudgetPeriodType::Monthly, 3, $this->d('2026-01-01'));

        $this->assertSame('2026-07-01..2026-09-30', $this->bounds($quarter, '2026-08-20'));
    }

    public function test_weekly_and_biweekly_cycles(): void
    {
        $weekly = new CyclePeriod(BudgetPeriodType::Weekly);
        $biweekly = new CyclePeriod(BudgetPeriodType::Weekly, 2, $this->d('2026-10-05'));

        $this->assertSame('2026-10-12..2026-10-18', $this->bounds($weekly, '2026-10-14'));
        $this->assertSame('2026-10-05..2026-10-18', $this->bounds($biweekly, '2026-10-14'));
        $this->assertSame('2026-10-19..2026-11-01', $this->bounds($biweekly, '2026-10-19'));
        $this->assertSame('2026-09-21..2026-10-04', $this->bounds($biweekly, '2026-10-01'), 'before the anchor too');
    }

    public function test_a_custom_number_of_days(): void
    {
        $cycle = new CyclePeriod(BudgetPeriodType::Daily, 10, $this->d('2026-10-01'));

        $this->assertSame('2026-10-11..2026-10-20', $this->bounds($cycle, '2026-10-15'));
        $this->assertSame('2026-09-21..2026-09-30', $this->bounds($cycle, '2026-09-30'));
    }

    public function test_a_yearly_cycle_can_start_mid_year(): void
    {
        $cycle = new CyclePeriod(BudgetPeriodType::Yearly, 1, $this->d('2020-08-01'));

        $this->assertSame('2026-08-01..2027-07-31', $this->bounds($cycle, '2026-10-12'));
        $this->assertSame('2025-08-01..2026-07-31', $this->bounds($cycle, '2026-03-01'));
    }

    public function test_completed_cycles_between_two_dates(): void
    {
        $cycle = new CyclePeriod(BudgetPeriodType::Monthly);

        $done = $cycle->completedBetween($this->d('2026-07-20'), $this->d('2026-10-12'));

        $this->assertSame(
            ['2026-07-01..2026-07-31', '2026-08-01..2026-08-31', '2026-09-01..2026-09-30'],
            array_map(fn (array $pair) => $pair[0]->format('Y-m-d').'..'.$pair[1]->format('Y-m-d'), $done),
        );
        $this->assertSame([], $cycle->completedBetween($this->d('2026-10-01'), $this->d('2026-10-31')), 'a cycle ending today is still running');
        $this->assertCount(1, $cycle->completedBetween($this->d('2026-10-01'), $this->d('2026-11-01')));
    }

    public function test_it_describes_itself_in_plain_language(): void
    {
        $this->assertSame('Every month from the 15th', (new CyclePeriod(BudgetPeriodType::Monthly, 1, $this->d('2026-01-15')))->describe());
        $this->assertSame('Every 2 weeks from Monday', (new CyclePeriod(BudgetPeriodType::Weekly, 2))->describe());
        $this->assertSame('Every 10 days', (new CyclePeriod(BudgetPeriodType::Daily, 10))->describe());
        $this->assertSame('Every year from August 1', (new CyclePeriod(BudgetPeriodType::Yearly, 1, $this->d('2020-08-01')))->describe());
    }

    public function test_a_cycle_must_repeat_at_least_once(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CyclePeriod(BudgetPeriodType::Monthly, 0);
    }
}
