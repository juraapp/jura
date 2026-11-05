<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\IncomeStep;
use App\Modules\Planning\Support\IncomeStepBucket;
use App\Modules\Shared\Support\Money;
use PHPUnit\Framework\TestCase;

class IncomeStepTest extends TestCase
{
    private function bucket(
        int $id,
        ?FillRule $rule,
        ?string $value = null,
        ?string $cap = null,
        bool $roomApplies = true,
        string $available = '0',
        ?string $received = null,
        bool $eligible = true,
    ): IncomeStepBucket {
        return new IncomeStepBucket(
            $id, $rule, $value, $cap === null ? null : Money::of($cap), $roomApplies, Money::of($available),
            $received === null ? null : Money::of($received), $eligible,
        );
    }

    /**
     * @param  array<int, Money>  $allocations
     * @return array<int, string>
     */
    private function amounts(array $allocations): array
    {
        return array_map(fn (Money $money) => $money->toDecimalString(), $allocations);
    }

    public function test_without_rules_or_overrides_the_whole_income_is_left_over(): void
    {
        $result = (new IncomeStep)->split(Money::of(1000), [$this->bucket(1, null), $this->bucket(2, null)]);

        $this->assertSame([], $result['allocations']);
        $this->assertSame('1000.00', $result['leftover']->toDecimalString());
    }

    public function test_ineligible_buckets_take_nothing_and_their_rules_do_not_count(): void
    {
        $step = new IncomeStep;

        $onlyIneligibleRule = $step->split(Money::of(1000), [$this->bucket(1, FillRule::Fixed, '300', eligible: false), $this->bucket(2, null)]);
        $this->assertSame([], $onlyIneligibleRule['allocations']);
        $this->assertSame('1000.00', $onlyIneligibleRule['leftover']->toDecimalString());

        $mixed = $step->split(Money::of(1000), [$this->bucket(1, FillRule::Fixed, '300', eligible: false), $this->bucket(2, FillRule::Fixed, '200')]);
        $this->assertSame([2 => '200.00'], $this->amounts($mixed['allocations']));
        $this->assertSame('800.00', $mixed['leftover']->toDecimalString());
    }

    public function test_room_is_the_cap_minus_what_the_bucket_holds_only_when_the_cap_limits_income(): void
    {
        $result = (new IncomeStep)->split(Money::of(1000), [
            $this->bucket(1, FillRule::Fixed, '500', cap: '600', available: '450'),
            $this->bucket(2, FillRule::Fixed, '300', cap: '100', roomApplies: false, available: '90'),
        ]);

        // Bucket 1 has 150 of room; bucket 2's cap is a spending limit, so it takes its full 300.
        $this->assertSame([1 => '150.00', 2 => '300.00'], $this->amounts($result['allocations']));
        $this->assertSame('550.00', $result['leftover']->toDecimalString());
    }

    public function test_a_bucket_already_over_its_cap_has_no_room(): void
    {
        $result = (new IncomeStep)->split(Money::of(500), [$this->bucket(1, FillRule::PercentOfRemaining, '100', cap: '300', available: '400')]);

        $this->assertSame([], $result['allocations']);
        $this->assertSame('500.00', $result['leftover']->toDecimalString());
    }

    public function test_a_fixed_rule_in_a_cycle_tops_up_to_its_amount(): void
    {
        $result = (new IncomeStep)->split(Money::of(1000), [$this->bucket(1, FillRule::Fixed, '400', received: '250')]);

        $this->assertSame([1 => '150.00'], $this->amounts($result['allocations']));
    }

    public function test_overrides_win_over_rules_and_ignore_ineligible_buckets(): void
    {
        $result = (new IncomeStep)->split(
            Money::of(1000),
            [$this->bucket(1, FillRule::Fixed, '900'), $this->bucket(2, null), $this->bucket(3, null, eligible: false)],
            [2 => Money::of(250), 3 => Money::of(400)],
        );

        $this->assertSame([2 => '250.00'], $this->amounts($result['allocations']));
        $this->assertSame('750.00', $result['leftover']->toDecimalString());
    }

    public function test_overrides_only_for_ineligible_buckets_fall_back_to_the_rules(): void
    {
        $result = (new IncomeStep)->split(
            Money::of(1000),
            [$this->bucket(1, FillRule::Fixed, '300'), $this->bucket(2, null, eligible: false)],
            [2 => Money::of(400)],
        );

        $this->assertSame([1 => '300.00'], $this->amounts($result['allocations']));
        $this->assertSame('700.00', $result['leftover']->toDecimalString());
    }
}
