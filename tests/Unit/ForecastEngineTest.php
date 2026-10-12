<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\Forecast\EngineBucket;
use App\Modules\Planning\Support\Forecast\EngineIncome;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\EngineLine;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Planning\Support\Waterfall;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ForecastEngineTest extends TestCase
{
    private const START = '2026-10-01';

    private function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    private function bucket(
        int $id,
        string|int $balance = 0,
        ?FillRule $rule = null,
        string|int|null $value = null,
        bool $default = false,
        ?array $accounts = null,
        string|int|null $target = null,
        bool $savings = true,
        ?BudgetPeriodType $cycle = null,
        CarryOverPolicy $carry = CarryOverPolicy::Carry,
        ?int $overflow = null,
    ): EngineBucket {
        return new EngineBucket(
            $id, $rule, $value === null ? null : (string) $value, $target === null ? null : Money::of($target), $savings,
            Money::of($balance), $accounts, $cycle, $carry, $overflow, $default,
        );
    }

    private function income(string $date, int $amount, ?int $account = null): EngineIncome
    {
        return new EngineIncome($this->date($date), Money::of($amount), $account);
    }

    private function line(int $id, int $price, ?int $bucket = null, ?string $notBefore = null, ?string $desiredBy = null, int $importance = 3, ?string $blocked = null): EngineLine
    {
        return new EngineLine(
            $id, "Item {$id}", Money::of($price), $bucket,
            $notBefore ? $this->date($notBefore) : null, $desiredBy ? $this->date($desiredBy) : null, $importance, $blocked,
        );
    }

    /**
     * @param  list<EngineBucket>  $buckets
     * @param  list<EngineIncome>  $incomes
     * @param  list<EngineLine>  $lines
     * @return array<int, array{date: ?string, delay: ?int, shortfall: string, late: bool}>
     */
    private function simulate(array $buckets, array $incomes, array $lines, string $end = '2027-03-31'): array
    {
        $result = (new ForecastEngine(new Waterfall))->run(
            new EngineInput($this->date(self::START), $this->date($end), $buckets, $incomes),
            $lines,
        );

        $out = [];
        foreach ($result->lines as $line) {
            $out[$line->itemId] = [
                'date' => $line->purchaseDate?->toDateString(),
                'delay' => $line->delayDays,
                'shortfall' => $line->shortfall->toDecimalString(),
                'late' => $line->late,
            ];
        }

        return $out;
    }

    public function test_an_item_the_bucket_can_already_afford_is_bought_today(): void
    {
        $out = $this->simulate([$this->bucket(1, 1000)], [], [$this->line(1, 800, 1)]);

        $this->assertSame(self::START, $out[1]['date']);
        $this->assertSame(0, $out[1]['delay']);
    }

    public function test_an_item_waits_for_the_income_that_funds_it(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 0, FillRule::Fixed, 500)],
            [$this->income('2026-10-10', 1000), $this->income('2026-11-10', 1000)],
            [$this->line(1, 900, 1)],
        );

        $this->assertSame('2026-11-10', $out[1]['date'], '500 on Oct 10 is not enough, 1000 total on Nov 10 is');
        $this->assertSame(40, $out[1]['delay']);
    }

    public function test_items_are_bought_strictly_in_the_given_order(): void
    {
        $buckets = [$this->bucket(1, 100, FillRule::Fixed, 500)];
        $incomes = [$this->income('2026-10-20', 1000)];

        $expensiveFirst = $this->simulate($buckets, $incomes, [$this->line(1, 500, 1), $this->line(2, 50, 1)]);
        $this->assertSame('2026-10-20', $expensiveFirst[1]['date']);
        $this->assertSame('2026-10-20', $expensiveFirst[2]['date'], 'the cheap item waits for the expensive one even though it was affordable today');

        $cheapFirst = $this->simulate($buckets, $incomes, [$this->line(2, 50, 1), $this->line(1, 500, 1)]);
        $this->assertSame(self::START, $cheapFirst[2]['date']);
        $this->assertSame('2026-10-20', $cheapFirst[1]['date']);
    }

    public function test_not_before_delays_a_purchase_even_when_funds_exist(): void
    {
        $out = $this->simulate([$this->bucket(1, 1000)], [], [$this->line(1, 100, 1, notBefore: '2026-12-15')]);

        $this->assertSame('2026-12-15', $out[1]['date']);
    }

    public function test_an_item_that_cannot_be_afforded_before_the_horizon_reports_its_shortfall(): void
    {
        $out = $this->simulate([$this->bucket(1, 100)], [], [$this->line(1, 700, 1)]);

        $this->assertNull($out[1]['date']);
        $this->assertNull($out[1]['delay']);
        $this->assertSame('600.00', $out[1]['shortfall']);
    }

    public function test_items_sharing_a_bucket_spend_the_same_money(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 600, FillRule::Fixed, 300)],
            [$this->income('2026-11-01', 1000), $this->income('2026-12-01', 1000)],
            [$this->line(1, 500, 1), $this->line(2, 500, 1)],
        );

        $this->assertSame(self::START, $out[1]['date']);
        $this->assertSame('2026-12-01', $out[2]['date'], '100 left + 300 + 300 = 700 only by December');
    }

    public function test_an_item_without_a_bucket_draws_from_the_default_bucket(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 0, FillRule::Fixed, 100), $this->bucket(2, 50, default: true)],
            [$this->income('2026-10-12', 1000)],
            [$this->line(1, 800)],
        );

        $this->assertSame('2026-10-12', $out[1]['date'], '50 + 900 left over after the fixed 100');
    }

    public function test_buckets_restricted_to_other_accounts_do_not_receive_the_income(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 0, FillRule::Percent, 100, accounts: [7])],
            [$this->income('2026-10-12', 1000, account: 8), $this->income('2026-10-20', 1000, account: 7)],
            [$this->line(1, 900, 1)],
        );

        $this->assertSame('2026-10-20', $out[1]['date']);
    }

    public function test_savings_buckets_stop_filling_at_their_target(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 0, FillRule::Percent, 100, target: 300)],
            [$this->income('2026-10-12', 1000), $this->income('2026-11-12', 1000)],
            [$this->line(1, 600, 1)],
        );

        $this->assertNull($out[1]['date'], 'capped at 300, so 600 is never reached');
        $this->assertSame('300.00', $out[1]['shortfall']);
    }

    public function test_income_fills_nothing_when_no_bucket_has_a_rule(): void
    {
        $out = $this->simulate([$this->bucket(1, 0)], [$this->income('2026-10-12', 1000)], [$this->line(1, 100, 1)]);

        $this->assertNull($out[1]['date']);
    }

    public function test_priority_order_decides_who_gets_a_short_income(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 0, FillRule::Fixed, 400), $this->bucket(2, 0, FillRule::Fixed, 400)],
            [$this->income('2026-10-12', 500)],
            [$this->line(1, 400, 1), $this->line(2, 400, 2)],
        );

        $this->assertSame('2026-10-12', $out[1]['date']);
        $this->assertNull($out[2]['date'], 'the second bucket only got the remaining 100');
    }

    public function test_a_blocked_item_is_skipped_and_does_not_hold_up_the_others(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 1000)],
            [],
            [$this->line(1, 100, 1, blocked: 'accounts'), $this->line(2, 100, 1)],
        );

        $this->assertNull($out[1]['date']);
        $this->assertSame(self::START, $out[2]['date']);
    }

    public function test_the_late_flag_marks_purchases_after_the_wanted_by_date(): void
    {
        $out = $this->simulate(
            [$this->bucket(1, 0, FillRule::Fixed, 1000)],
            [$this->income('2026-12-10', 1000)],
            [$this->line(1, 800, 1, desiredBy: '2026-11-30'), $this->line(2, 100, 1, desiredBy: '2027-02-01')],
        );

        $this->assertTrue($out[1]['late']);
        $this->assertFalse($out[2]['late']);
    }

    public function test_release_at_the_end_of_a_cycle_moves_leftovers_to_the_default_bucket(): void
    {
        $out = $this->simulate(
            [
                $this->bucket(1, 300, cycle: BudgetPeriodType::Monthly, carry: CarryOverPolicy::Release),
                $this->bucket(2, 0, default: true),
            ],
            [],
            [$this->line(1, 300, 2)],
        );

        $this->assertSame('2026-10-31', $out[1]['date'], 'the October leftover reaches the default bucket on the last day of the month');
    }

    public function test_overflow_at_the_end_of_a_cycle_moves_leftovers_to_the_chosen_bucket(): void
    {
        $out = $this->simulate(
            [
                $this->bucket(1, 300, cycle: BudgetPeriodType::Monthly, carry: CarryOverPolicy::Overflow, overflow: 3),
                $this->bucket(2, 0, default: true),
                $this->bucket(3, 0),
            ],
            [],
            [$this->line(1, 300, 3)],
        );

        $this->assertSame('2026-10-31', $out[1]['date']);
    }

    public function test_the_result_reports_each_buckets_balance_at_the_end(): void
    {
        $result = (new ForecastEngine(new Waterfall))->run(
            new EngineInput($this->date(self::START), $this->date('2026-12-31'), [$this->bucket(1, 1000), $this->bucket(2, 5, default: true)], []),
            [$this->line(1, 400, 1)],
        );

        $this->assertSame('600.00', $result->endBalances[1]->toDecimalString());
        $this->assertSame('5.00', $result->endBalances[2]->toDecimalString());
    }
}
