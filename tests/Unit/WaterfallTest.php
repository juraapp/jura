<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\Waterfall;
use App\Modules\Planning\Support\WaterfallBucket;
use App\Modules\Shared\Support\Money;
use PHPUnit\Framework\TestCase;

class WaterfallTest extends TestCase
{
    private function bucket(int $id, ?FillRule $rule, ?string $value = null, ?string $room = null): WaterfallBucket
    {
        return new WaterfallBucket($id, $rule, $value, $room === null ? null : Money::of($room));
    }

    /**
     * @param  array<int, Money>  $allocations
     * @return array<int, string>
     */
    private function amounts(array $allocations): array
    {
        return array_map(fn (Money $money) => $money->toDecimalString(), $allocations);
    }

    public function test_fixed_rules_claim_their_amount_in_priority_order(): void
    {
        $result = (new Waterfall)->distribute(Money::of(1000), [
            $this->bucket(1, FillRule::Fixed, '300'),
            $this->bucket(2, FillRule::Fixed, '200'),
        ]);

        $this->assertSame([1 => '300.00', 2 => '200.00'], $this->amounts($result['allocations']));
        $this->assertSame('500.00', $result['leftover']->toDecimalString());
    }

    public function test_percent_rules_take_a_share_of_the_whole_income_not_of_what_is_left(): void
    {
        $result = (new Waterfall)->distribute(Money::of(1000), [
            $this->bucket(1, FillRule::Percent, '50'),
            $this->bucket(2, FillRule::Percent, '20'),
        ]);

        $this->assertSame([1 => '500.00', 2 => '200.00'], $this->amounts($result['allocations']));
        $this->assertSame('300.00', $result['leftover']->toDecimalString());
    }

    public function test_earlier_buckets_get_first_claim_when_income_runs_out(): void
    {
        $result = (new Waterfall)->distribute(Money::of(400), [
            $this->bucket(1, FillRule::Fixed, '300'),
            $this->bucket(2, FillRule::Fixed, '300'),
            $this->bucket(3, FillRule::Fixed, '300'),
        ]);

        $this->assertSame([1 => '300.00', 2 => '100.00'], $this->amounts($result['allocations']));
        $this->assertTrue($result['leftover']->isZero());
    }

    public function test_fill_to_target_only_tops_up_the_missing_room(): void
    {
        $result = (new Waterfall)->distribute(Money::of(1000), [
            $this->bucket(1, FillRule::FillToTarget, null, '350'),
        ]);

        $this->assertSame([1 => '350.00'], $this->amounts($result['allocations']));
        $this->assertSame('650.00', $result['leftover']->toDecimalString());
    }

    public function test_fill_to_target_with_no_room_or_no_target_gets_nothing(): void
    {
        $result = (new Waterfall)->distribute(Money::of(1000), [
            $this->bucket(1, FillRule::FillToTarget, null, '0'),
            $this->bucket(2, FillRule::FillToTarget, null, null),
        ]);

        $this->assertSame([], $result['allocations']);
        $this->assertSame('1000.00', $result['leftover']->toDecimalString());
    }

    public function test_room_caps_fixed_and_percent_rules_so_goals_stop_at_their_target(): void
    {
        $result = (new Waterfall)->distribute(Money::of(1000), [
            $this->bucket(1, FillRule::Fixed, '400', '150'),
            $this->bucket(2, FillRule::Percent, '50', '100'),
        ]);

        $this->assertSame([1 => '150.00', 2 => '100.00'], $this->amounts($result['allocations']));
        $this->assertSame('750.00', $result['leftover']->toDecimalString());
    }

    public function test_buckets_without_a_rule_are_skipped(): void
    {
        $result = (new Waterfall)->distribute(Money::of(100), [
            $this->bucket(1, null),
            $this->bucket(2, FillRule::Fixed, '40'),
        ]);

        $this->assertSame([2 => '40.00'], $this->amounts($result['allocations']));
    }

    public function test_percentage_cents_that_do_not_divide_evenly_stay_in_the_leftover(): void
    {
        $result = (new Waterfall)->distribute(Money::of('100.01'), [
            $this->bucket(1, FillRule::Percent, '33.33'),
        ]);

        $this->assertSame([1 => '33.33'], $this->amounts($result['allocations']));
        $this->assertSame('66.68', $result['leftover']->toDecimalString());
    }

    public function test_overrides_replace_the_rules_and_are_capped_by_the_income(): void
    {
        $result = (new Waterfall)->distribute(
            Money::of(500),
            [$this->bucket(1, FillRule::Percent, '100')],
            [2 => Money::of(300), 3 => Money::of(400)],
        );

        $this->assertSame([2 => '300.00', 3 => '200.00'], $this->amounts($result['allocations']));
        $this->assertTrue($result['leftover']->isZero());
    }

    public function test_an_income_of_zero_or_less_allocates_nothing(): void
    {
        $result = (new Waterfall)->distribute(Money::zero(), [$this->bucket(1, FillRule::Fixed, '50')]);

        $this->assertSame([], $result['allocations']);
        $this->assertTrue($result['leftover']->isZero());
    }

    public function test_a_fixed_rule_with_a_cycle_only_tops_up_what_the_cycle_has_not_received_yet(): void
    {
        $result = (new Waterfall)->distribute(Money::of(1000), [
            new WaterfallBucket(1, FillRule::Fixed, '150', null, Money::of(100)),
            new WaterfallBucket(2, FillRule::Fixed, '150', null, Money::of(150)),
        ]);

        $this->assertSame([1 => '50.00'], $this->amounts($result['allocations']));
        $this->assertSame('950.00', $result['leftover']->toDecimalString());
    }

    public function test_share_of_what_is_left_is_taken_after_earlier_buckets(): void
    {
        $result = (new Waterfall)->distribute(Money::of(1000), [
            $this->bucket(1, FillRule::Fixed, '200'),
            $this->bucket(2, FillRule::PercentOfRemaining, '50', '300'),
            $this->bucket(3, FillRule::PercentOfRemaining, '100'),
        ]);

        $this->assertSame([1 => '200.00', 2 => '300.00', 3 => '500.00'], $this->amounts($result['allocations']));
        $this->assertSame('0.00', $result['leftover']->toDecimalString());
    }
}
