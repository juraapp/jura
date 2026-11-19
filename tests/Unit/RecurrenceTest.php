<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Support\Recurrence;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RecurrenceTest extends TestCase
{
    /**
     * @param  list<string>  $excluded
     * @return list<string>
     */
    private function dates(
        RecurringFrequency $frequency,
        string $start,
        string $until,
        ?string $endsOn = null,
        array $excluded = [],
        ?string $after = null,
        ?string $from = null,
        int $max = 120,
    ): array {
        $parse = fn (?string $date) => $date !== null ? CarbonImmutable::parse($date) : null;

        return array_map(
            fn (CarbonImmutable $date) => $date->toDateString(),
            Recurrence::occurrences($frequency, CarbonImmutable::parse($start), $parse($endsOn), $excluded, $parse($after), $parse($from), CarbonImmutable::parse($until), $max),
        );
    }

    public function test_monthly_dates_keep_the_month_end_without_drifting(): void
    {
        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'],
            $this->dates(RecurringFrequency::Monthly, '2026-01-31', '2026-04-30'),
        );
    }

    public function test_yearly_dates_from_a_leap_day_fall_on_february_28_until_the_next_leap_year(): void
    {
        $this->assertSame(
            ['2028-02-29', '2029-02-28', '2030-02-28', '2031-02-28', '2032-02-29'],
            $this->dates(RecurringFrequency::Yearly, '2028-02-29', '2032-12-31'),
        );
    }

    public function test_weekly_and_biweekly_steps(): void
    {
        $this->assertSame(['2026-11-02', '2026-11-09', '2026-11-16'], $this->dates(RecurringFrequency::Weekly, '2026-11-02', '2026-11-20'));
        $this->assertSame(['2026-11-02', '2026-11-16', '2026-11-30'], $this->dates(RecurringFrequency::Biweekly, '2026-11-02', '2026-12-13'));
    }

    public function test_excluded_dates_are_skipped_and_the_end_date_caps_the_horizon(): void
    {
        $this->assertSame(
            ['2026-01-15', '2026-03-15'],
            $this->dates(RecurringFrequency::Monthly, '2026-01-15', '2026-12-31', endsOn: '2026-04-01', excluded: ['2026-02-15']),
        );
    }

    public function test_after_is_exclusive_and_from_is_inclusive(): void
    {
        $this->assertSame(['2026-03-10', '2026-04-10'], $this->dates(RecurringFrequency::Monthly, '2026-01-10', '2026-04-30', after: '2026-02-10'));
        $this->assertSame(['2026-02-10', '2026-03-10'], $this->dates(RecurringFrequency::Monthly, '2026-01-10', '2026-03-31', from: '2026-02-10'));
    }

    public function test_max_caps_the_number_of_dates(): void
    {
        $this->assertCount(3, $this->dates(RecurringFrequency::Weekly, '2026-01-01', '2030-01-01', max: 3));
    }
}
