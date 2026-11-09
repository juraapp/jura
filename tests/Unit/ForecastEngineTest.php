<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\Forecast\EngineBucket;
use App\Modules\Planning\Support\Forecast\EngineFlow;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\EngineLine;
use App\Modules\Planning\Support\Forecast\EngineResult;
use App\Modules\Planning\Support\Forecast\FlowKind;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Every expected number here is worked out by hand in the comments; free money = cash − funded envelopes.
 */
class ForecastEngineTest extends TestCase
{
    private const START = '2026-10-01';

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    /**
     * @param  array<string, mixed>  $options  EngineBucket named arguments
     */
    private function bucket(int $id, int|string $envelope = 0, array $options = []): EngineBucket
    {
        return new EngineBucket(...array_merge([
            'id' => $id, 'rule' => null, 'value' => null, 'cap' => null, 'isSavings' => true, 'envelope' => Money::of($envelope),
        ], $options));
    }

    private function income(string $date, int $amount, array $overrides = [], ?int $account = null): EngineFlow
    {
        return new EngineFlow($this->day($date), 0, FlowKind::Income, Money::of($amount), $account, null, array_map(fn ($value) => Money::of($value), $overrides));
    }

    private function expense(string $date, int $amount, ?int $category = null): EngineFlow
    {
        return new EngineFlow($this->day($date), 1, FlowKind::Expense, Money::of($amount), null, $category);
    }

    /**
     * @param  list<EngineBucket>  $buckets
     * @param  list<EngineFlow>  $flows
     */
    private function input(int $cash, array $buckets = [], array $flows = [], string $end = '2027-03-31'): EngineInput
    {
        return new EngineInput($this->day(self::START), $this->day($end), Money::of($cash), $buckets, $flows);
    }

    private function line(int $id, int $price, ?int $bucketId = null, ?string $notBefore = null): EngineLine
    {
        return new EngineLine($id, "Item {$id}", Money::of($price), $bucketId, $notBefore ? $this->day($notBefore) : null, null, 3);
    }

    /**
     * @param  list<EngineLine>  $lines
     */
    private function simulate(EngineInput $input, array $lines = []): EngineResult
    {
        return (new ForecastEngine)->run($input, $lines);
    }

    private function money(Money $money): string
    {
        return $money->toDecimalString();
    }

    public function test_without_buckets_an_income_makes_an_item_affordable_from_ready_to_assign(): void
    {
        // 100 in cash, the 500 item waits for the 500 income on the 15th.
        $result = $this->simulate($this->input(100, [], [$this->income('2026-10-15', 500)]), [$this->line(1, 500)]);

        $line = $result->lines[0];
        $this->assertSame('2026-10-15', $line->purchaseDate->toDateString());
        $this->assertSame(14, $line->delayDays);
        $this->assertSame(['0.00', '500.00'], [$this->money($line->fromBucket), $this->money($line->fromFree)]);
        $this->assertSame('100.00', $this->money($result->endReadyToAssign));
        $this->assertSame('100.00', $this->money($result->endCash));
    }

    public function test_an_item_is_paid_from_its_bucket_first_and_the_rest_from_ready_to_assign(): void
    {
        // Cash 1,000, the bucket holds 300, so 700 is free. The 500 item takes 300 from the bucket and 200 from free.
        $result = $this->simulate($this->input(1000, [$this->bucket(1, 300)]), [$this->line(1, 500, 1)]);

        $line = $result->lines[0];
        $this->assertSame(self::START, $line->purchaseDate->toDateString());
        $this->assertSame(['300.00', '200.00'], [$this->money($line->fromBucket), $this->money($line->fromFree)]);
        $this->assertSame('0.00', $this->money($result->endBalances[1]));
        $this->assertSame('500.00', $this->money($result->endReadyToAssign));
    }

    public function test_a_bucket_capped_below_the_price_is_topped_up_from_ready_to_assign(): void
    {
        // The income of 1,000 fills the bucket to its 400 cap and leaves 600 free; the 600 item takes 400 + 200.
        $bucket = $this->bucket(1, 0, ['rule' => FillRule::PercentOfRemaining, 'value' => '100', 'cap' => Money::of(400)]);

        $result = $this->simulate($this->input(0, [$bucket], [$this->income('2026-10-15', 1000)]), [$this->line(1, 600, 1)]);

        $line = $result->lines[0];
        $this->assertSame('2026-10-15', $line->purchaseDate->toDateString());
        $this->assertSame(['400.00', '200.00'], [$this->money($line->fromBucket), $this->money($line->fromFree)]);
        $this->assertSame('400.00', $this->money($result->endReadyToAssign));
    }

    public function test_a_later_planned_expense_holds_a_purchase_back_until_the_cash_is_there(): void
    {
        // Cash 1,000 today, but 800 leaves on Nov 10: from today the lowest balance is 200, too little for 500.
        // On Dec 1 the 1,000 income lifts every later balance to 1,200, so the item is bought then.
        $result = $this->simulate(
            $this->input(1000, [], [$this->expense('2026-11-10', 800), $this->income('2026-12-01', 1000)]),
            [$this->line(1, 500)],
        );

        $this->assertSame('2026-12-01', $result->lines[0]->purchaseDate->toDateString());
        $this->assertSame('700.00', $this->money($result->endCash));
    }

    public function test_the_cash_guard_uses_the_lowest_balance_from_the_day_on_not_the_balance_that_day(): void
    {
        // Balances: Oct 1 1,000, Oct 20 3,000, Nov 5 500, Dec 1 800. On Oct 20 there are 3,000 but the lowest balance
        // from then on is 500 (< 700), so the 700 item waits until Dec 1, when the lowest from then on is 800.
        $result = $this->simulate(
            $this->input(1000, [], [$this->income('2026-10-20', 2000), $this->expense('2026-11-05', 2500), $this->income('2026-12-01', 300)]),
            [$this->line(1, 700)],
        );

        $this->assertSame('2026-12-01', $result->lines[0]->purchaseDate->toDateString());
    }

    public function test_an_item_that_free_money_covers_but_cash_does_not_is_limited_by_cash(): void
    {
        // An overspent envelope (-300) is negative earmarked money: free = 1,000 + 300 = 1,300, but only 1,000 is cash.
        $overspent = $this->bucket(1, -300, ['isSavings' => false, 'categoryId' => 7]);

        $result = $this->simulate($this->input(1000, [$overspent], [], '2026-12-31'), [$this->line(1, 1200)]);

        $line = $result->lines[0];
        $this->assertNull($line->purchaseDate);
        $this->assertSame('cash', $line->limitedBy);
        $this->assertSame('200.00', $this->money($line->shortfall));
    }

    public function test_an_item_waiting_for_free_money_says_so_and_shows_what_is_missing(): void
    {
        // 300 is earmarked out of 1,000: 700 free, 200 short of the 900 item.
        $result = $this->simulate($this->input(1000, [$this->bucket(1, 300)], [], '2026-12-31'), [$this->line(1, 900)]);

        $this->assertSame('free', $result->lines[0]->limitedBy);
        $this->assertSame('200.00', $this->money($result->lines[0]->shortfall));
    }

    public function test_a_cycle_cap_override_changes_the_room_in_that_cycle_only(): void
    {
        // October's cap is 500: the bucket takes 500. November's cap is 800: it takes 300 more. Free = 2,000 - 800.
        $bucket = $this->bucket(1, 0, [
            'rule' => FillRule::FillToTarget, 'cap' => Money::of(500), 'isSavings' => false, 'cycle' => BudgetPeriodType::Monthly,
            'capOverrides' => ['2026-11-01' => Money::of(800)],
        ]);

        $result = $this->simulate($this->input(0, [$bucket], [$this->income('2026-10-15', 1000), $this->income('2026-11-15', 1000)], '2026-11-30'));

        $this->assertSame('800.00', $this->money($result->endBalances[1]));
        $this->assertSame('1200.00', $this->money($result->endReadyToAssign));
    }

    public function test_a_planned_incomes_explicit_split_wins_over_the_rules(): void
    {
        $bucket = $this->bucket(1, 0, ['rule' => FillRule::Fixed, 'value' => '900']);

        $result = $this->simulate($this->input(0, [$bucket], [$this->income('2026-10-15', 1000, [1 => 250])], '2026-10-31'));

        $this->assertSame('250.00', $this->money($result->endBalances[1]));
        $this->assertSame('750.00', $this->money($result->endReadyToAssign));
        $this->assertSame(['1', '1000.00', '250.00', '750.00'], [
            (string) $result->incomes->counted, $this->money($result->incomes->amount), $this->money($result->incomes->allocated), $this->money($result->incomes->leftFree),
        ]);
    }

    public function test_a_planned_category_expense_comes_out_of_funded_buckets_not_limit_only_ones(): void
    {
        // Funded Food envelope 300, limit-only Food bucket. Cash 1,000: free 700. A 100 Food expense takes 100 from
        // the funded envelope and from cash: free stays 700.
        $funded = $this->bucket(1, 300, ['isSavings' => false, 'categoryId' => 7]);
        $limit = $this->bucket(2, 0, ['isSavings' => false, 'categoryId' => 7, 'isFunded' => false]);

        $result = $this->simulate($this->input(1000, [$funded, $limit], [$this->expense('2026-10-10', 100, 7)], '2026-10-20'));

        $this->assertSame('200.00', $this->money($result->endBalances[1]));
        $this->assertSame('700.00', $this->money($result->endReadyToAssign));
        $this->assertSame('900.00', $this->money($result->endCash));
        $this->assertArrayNotHasKey(2, $result->timeline[0]->envelopes(), 'a limit-only bucket holds no money');
    }

    public function test_a_limit_only_bucket_starts_its_envelope_from_zero_on_its_first_allocation(): void
    {
        // Never funded, with 150 of spending behind it. The 500 income gives it 200 (its fixed rule): its envelope
        // is 200, not 50. A later 50 Food expense leaves 150. Free = 1,500 - 200 = 1,300, and stays 1,300.
        $bucket = $this->bucket(1, -150, ['isSavings' => false, 'categoryId' => 7, 'isFunded' => false, 'rule' => FillRule::Fixed, 'value' => '200']);

        $result = $this->simulate($this->input(1000, [$bucket], [$this->income('2026-10-10', 500), $this->expense('2026-10-12', 50, 7)], '2026-10-20'));

        $this->assertSame('200.00', $this->money($result->pointAt($this->day('2026-10-10'))->envelopes()[1]));
        $this->assertSame('150.00', $this->money($result->endBalances[1]));
        $this->assertSame('1300.00', $this->money($result->endReadyToAssign));
    }

    public function test_cycle_ends_release_to_ready_to_assign_or_overflow_into_another_bucket(): void
    {
        // Groceries (400) overflows into Savings when October ends, Fun (100) returns to ready to assign: both move on
        // Nov 1, the day after, like the real carry-over rows.
        $groceries = $this->bucket(1, 400, ['isSavings' => false, 'cycle' => BudgetPeriodType::Monthly, 'carryOver' => CarryOverPolicy::Overflow, 'overflowBucketId' => 3]);
        $fun = $this->bucket(2, 100, ['isSavings' => false, 'cycle' => BudgetPeriodType::Monthly, 'carryOver' => CarryOverPolicy::Release]);
        $savings = $this->bucket(3, 0);

        $result = $this->simulate($this->input(1000, [$groceries, $fun, $savings], [], '2026-11-15'));

        $this->assertSame(['0.00', '0.00', '400.00'], array_map(fn (Money $money) => $this->money($money), array_values($result->endBalances)));
        $this->assertSame('600.00', $this->money($result->endReadyToAssign));
        $this->assertSame('500.00', $this->money($result->pointAt($this->day('2026-10-31'))->free), 'on the last day of the cycle');
        $this->assertSame('600.00', $this->money($result->pointAt($this->day('2026-11-01'))->free), 'the day after');

        $cycle = $result->cycles[1];
        $this->assertSame(['2026-10-01', '2026-10-31', '0.00', '400.00', '400.00', '0.00', '0.00'], [
            $cycle->start->toDateString(), $cycle->end->toDateString(), $this->money($cycle->provisional), $this->money($cycle->projectedEnd),
            $this->money($cycle->released), $this->money($cycle->receivedFromOthers), $this->money($cycle->close),
        ]);
        $this->assertSame(['0.00', '400.00', '400.00'], [
            $this->money($result->cycles[3]->projectedEnd), $this->money($result->cycles[3]->receivedFromOthers), $this->money($result->cycles[3]->close),
        ]);
    }

    public function test_cycles_ending_the_same_day_pass_their_leftover_on_once(): void
    {
        // A (100) overflows into B, B (50) into C, C (30) releases; all monthly. On Nov 1 each leftover is measured
        // before anything moves: A's 100 lands in B, B's 50 in C, C's 30 goes back to ready to assign.
        $a = $this->bucket(1, 100, ['isSavings' => false, 'cycle' => BudgetPeriodType::Monthly, 'carryOver' => CarryOverPolicy::Overflow, 'overflowBucketId' => 2]);
        $b = $this->bucket(2, 50, ['isSavings' => false, 'cycle' => BudgetPeriodType::Monthly, 'carryOver' => CarryOverPolicy::Overflow, 'overflowBucketId' => 3]);
        $c = $this->bucket(3, 30, ['isSavings' => false, 'cycle' => BudgetPeriodType::Monthly, 'carryOver' => CarryOverPolicy::Release]);

        $result = $this->simulate($this->input(1000, [$a, $b, $c], [], '2026-11-15'));

        $this->assertSame(['0.00', '100.00', '50.00'], array_map(fn (Money $money) => $this->money($money), array_values($result->endBalances)));
        $this->assertSame('850.00', $this->money($result->endReadyToAssign));
    }

    public function test_an_item_bought_on_the_last_day_of_its_buckets_cycle_is_paid_from_the_bucket(): void
    {
        // The bucket (500, releases monthly) still holds its money on Oct 31: the 500 item waiting until then is paid
        // from it, and nothing is left to release on Nov 1.
        $bucket = $this->bucket(1, 500, ['isSavings' => false, 'cycle' => BudgetPeriodType::Monthly, 'carryOver' => CarryOverPolicy::Release]);

        $result = $this->simulate($this->input(500, [$bucket], [], '2026-11-30'), [$this->line(1, 500, 1, '2026-10-31')]);

        $this->assertSame(['2026-10-31', '500.00', '0.00'], [
            $result->lines[0]->purchaseDate->toDateString(), $this->money($result->lines[0]->fromBucket), $this->money($result->lines[0]->fromFree),
        ]);
        $this->assertSame('0.00', $this->money($result->endReadyToAssign));
    }

    public function test_a_never_funded_fill_to_target_bucket_fills_to_its_target_not_past_its_spending(): void
    {
        // Limit-only spending bucket, 150 spent against a 600 target. The 1,000 income funds it: its envelope starts
        // from zero, so it takes 600 (not 750); 400 stays free.
        $bucket = $this->bucket(1, -150, ['isSavings' => false, 'categoryId' => 7, 'isFunded' => false, 'rule' => FillRule::FillToTarget, 'cap' => Money::of(600)]);

        $result = $this->simulate($this->input(0, [$bucket], [$this->income('2026-10-10', 1000)], '2026-10-20'));

        $this->assertSame('600.00', $this->money($result->endBalances[1]));
        $this->assertSame('400.00', $this->money($result->endReadyToAssign));
    }

    public function test_a_bucket_that_completes_on_purchase_closes_even_when_it_paid_nothing(): void
    {
        // The bucket was never funded (Fixed 100, no cycle). The 900 item is bought today from free money; the bucket
        // completes anyway, so the three 500 incomes stay free: 1,000 - 900 + 1,500 = 1,600.
        $bucket = $this->bucket(1, 0, ['rule' => FillRule::Fixed, 'value' => '100', 'completesOnPurchase' => true, 'isFunded' => false]);

        $result = $this->simulate($this->input(1000, [$bucket], [$this->income('2026-10-15', 500), $this->income('2026-11-15', 500), $this->income('2026-12-15', 500)], '2026-12-31'), [$this->line(1, 900, 1)]);

        $this->assertSame(self::START, $result->lines[0]->purchaseDate->toDateString());
        $this->assertSame('1600.00', $this->money($result->endReadyToAssign));
        $this->assertSame('0.00', $this->money($result->endBalances[1]));
    }

    public function test_a_bucket_that_completes_on_purchase_closes_and_returns_what_is_left(): void
    {
        // Bike holds 1,200 of 1,500 cash. The 1,000 bike is paid from it, the 200 left returns to ready to assign
        // (free 500), and the closed bucket takes nothing from the later income: free ends at 900.
        $bike = $this->bucket(1, 1200, ['completesOnPurchase' => true, 'rule' => FillRule::Fixed, 'value' => '400']);

        $result = $this->simulate($this->input(1500, [$bike], [$this->income('2026-10-10', 400)], '2026-10-31'), [$this->line(1, 1000, 1)]);

        $this->assertSame(self::START, $result->lines[0]->purchaseDate->toDateString());
        $this->assertSame('500.00', $this->money($result->pointAt($this->day(self::START))->free));
        $this->assertSame('0.00', $this->money($result->endBalances[1]));
        $this->assertSame('900.00', $this->money($result->endReadyToAssign));
        $this->assertSame(self::START, $result->reached[1]->toDateString());
    }

    public function test_a_step_up_applies_from_the_cycle_after_the_bucket_it_waits_for_is_full(): void
    {
        // Emergency (cap 300) fills on Oct 5. Holiday takes 100 in October, then its step-up of 250 in Nov and Dec.
        $emergency = $this->bucket(1, 0, ['rule' => FillRule::Fixed, 'value' => '300', 'cap' => Money::of(300)]);
        $holiday = $this->bucket(2, 0, [
            'rule' => FillRule::Fixed, 'value' => '100', 'cycle' => BudgetPeriodType::Monthly, 'stepUpValue' => '250', 'stepUpAfterBucketId' => 1,
        ]);
        $flows = [$this->income('2026-10-05', 1000), $this->income('2026-11-05', 1000), $this->income('2026-12-05', 1000)];

        $result = $this->simulate($this->input(0, [$emergency, $holiday], $flows, '2026-12-31'));

        $this->assertSame('2026-10-05', $result->reached[1]->toDateString());
        $this->assertSame('600.00', $this->money($result->endBalances[2]));
        $this->assertSame('2100.00', $this->money($result->endReadyToAssign));
    }

    public function test_a_step_up_that_already_applies_at_the_start_keeps_applying(): void
    {
        // The real ledger says Holiday's step-up already applies (Emergency filled last month): 250 from the first income.
        $holiday = $this->bucket(1, 0, ['rule' => FillRule::Fixed, 'value' => '100', 'cycle' => BudgetPeriodType::Monthly,
            'stepUpValue' => '250', 'stepUpAfterBucketId' => 2, 'stepUpActive' => true]);
        $emergency = $this->bucket(2, 300, ['cap' => Money::of(300)]);

        $result = $this->simulate($this->input(300, [$holiday, $emergency], [$this->income('2026-10-05', 1000)], '2026-10-31'));

        $this->assertSame('250.00', $this->money($result->endBalances[1]));
    }

    public function test_items_are_bought_strictly_in_order_and_never_before_their_not_before_date(): void
    {
        // Both are affordable today, but the first waits until Oct 20, and the second may not jump ahead of it.
        $result = $this->simulate($this->input(1000), [$this->line(1, 800, null, '2026-10-20'), $this->line(2, 100)]);

        $this->assertSame(['2026-10-20', '2026-10-20'], [$result->lines[0]->purchaseDate->toDateString(), $result->lines[1]->purchaseDate->toDateString()]);
    }

    public function test_an_item_that_cannot_be_bought_in_time_is_parked_at_the_end_when_asked(): void
    {
        // 1,000 free and no income: the 5,000 TV can never be bought, so in strict order it blocks the 300 lamp and the
        // 200 rug. Parked, it moves to the end and the other two are bought today.
        $lines = [$this->line(1, 5000), $this->line(2, 300), $this->line(3, 200)];

        $strict = $this->simulate($this->input(1000, [], [], '2026-12-31'), $lines);
        $this->assertNull($strict->lines[1]->purchaseDate);

        $parked = (new ForecastEngine)->run($this->input(1000, [], [], '2026-12-31'), $lines, parkUnaffordable: true);

        $this->assertSame([2, 3, 1], array_map(fn ($line) => $line->itemId, $parked->lines));
        $this->assertSame([self::START, self::START, null], array_map(fn ($line) => $line->purchaseDate?->toDateString(), $parked->lines));
        $this->assertSame([false, false, true], array_map(fn ($line) => $line->parked, $parked->lines));
        $this->assertSame('4500.00', $this->money($parked->lines[2]->shortfall), '5,000 - the 500 left after the lamp and the rug');
    }

    public function test_parking_stops_once_only_parked_items_follow(): void
    {
        // Both are out of reach: the first is parked once, then the second is first and only a parked item follows it.
        $parked = (new ForecastEngine)->run($this->input(100, [], [], '2026-12-31'), [$this->line(1, 5000), $this->line(2, 6000)], parkUnaffordable: true);

        $this->assertSame([2, 1], array_map(fn ($line) => $line->itemId, $parked->lines));
        $this->assertSame([false, true], array_map(fn ($line) => $line->parked, $parked->lines));
    }

    public function test_a_purchase_that_leaves_ready_to_assign_below_zero_later_is_flagged(): void
    {
        // Cash 1,000, 500 earmarked: 500 free. The 400 item is bought today (the lowest cash later is 700). After the
        // 300 expense on Nov 1 there is 300 cash for 500 earmarked: free is -200 from that day.
        $result = $this->simulate($this->input(1000, [$this->bucket(1, 500)], [$this->expense('2026-11-01', 300)], '2026-12-31'), [$this->line(1, 400)]);

        $this->assertSame(self::START, $result->lines[0]->purchaseDate->toDateString());
        $this->assertSame('2026-11-01', $result->lines[0]->freeDipsOn->toDateString());
        $this->assertSame('-200.00', $this->money($result->endReadyToAssign));
    }

    public function test_ready_to_assign_at_the_start_is_cash_minus_every_funded_envelope(): void
    {
        // 1,000 - 300 - (-20); the never-funded bucket's -50 is a limit, not money.
        $input = $this->input(1000, [
            $this->bucket(1, 300),
            $this->bucket(2, -50, ['isSavings' => false, 'categoryId' => 7, 'isFunded' => false]),
            $this->bucket(3, -20, ['isSavings' => false, 'categoryId' => 8]),
        ]);

        $result = $this->simulate($input);

        $this->assertSame('720.00', $this->money($input->startReadyToAssign()));
        $this->assertSame('720.00', $this->money($result->timeline[0]->free));
        $this->assertSame('280.00', $this->money($result->timeline[0]->earmarked));
    }

    public function test_an_income_from_an_account_a_bucket_does_not_accept_stays_free(): void
    {
        $bucket = $this->bucket(1, 0, ['rule' => FillRule::Fixed, 'value' => '300', 'allowedAccountIds' => [5]]);

        $result = $this->simulate($this->input(0, [$bucket], [$this->income('2026-10-10', 1000, [], 6), $this->income('2026-10-12', 1000, [], 5)], '2026-10-31'));

        $this->assertSame('300.00', $this->money($result->endBalances[1]));
        $this->assertSame('1700.00', $this->money($result->endReadyToAssign));
    }

    public function test_a_blocked_item_is_never_bought_and_does_not_hold_back_the_next(): void
    {
        $blocked = new EngineLine(1, 'Blocked', Money::of(100), null, null, null, 3, 'accounts');

        $result = $this->simulate($this->input(1000), [$blocked, $this->line(2, 100)]);

        $this->assertNull($result->lines[0]->purchaseDate);
        $this->assertSame('accounts', $result->lines[0]->blocked);
        $this->assertSame('100.00', $this->money($result->lines[0]->shortfall));
        $this->assertSame(self::START, $result->lines[1]->purchaseDate->toDateString());
    }
}
