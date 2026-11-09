<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Contracts\SavingsTargets;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Whether savings buckets with a target date are on track. Expected values are worked out by hand in the comments.
 */
class SavingsTargetsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000, 'created_at' => '2026-01-01']);
        $this->actingAs($this->user);
    }

    private function bucket(array $attributes = [], int $saved = 0, ?User $owner = null): Bucket
    {
        $owner ??= $this->user;
        $bucket = Bucket::factory()->savings()->for($owner)->create($attributes + [
            'name' => 'Trip', 'currency' => 'COP', 'cap_amount' => 1000000, 'target_date' => '2027-01-12', 'created_at' => '2026-09-01',
        ]);

        if ($saved > 0) {
            Allocation::factory()->for($owner)->for($bucket)->create(['amount' => $saved, 'currency' => 'COP']);
        }

        return $bucket;
    }

    private function income(string $date, int $amount): void
    {
        PlannedEvent::factory()->for($this->user)->create(['type' => 'income', 'amount' => $amount, 'currency' => 'COP', 'date' => $date, 'certainty' => Certainty::Confirmed]);
    }

    /**
     * @return array<string, mixed>
     */
    private function target(string $name = 'Trip'): array
    {
        foreach (app(SavingsTargets::class)->targets($this->user) as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }

        $this->fail("No target for {$name}");
    }

    public function test_a_bucket_without_a_plan_is_judged_by_an_even_pace(): void
    {
        // Made on Sep 1, target Jan 12: 133 days, 41 of them gone (30.8%). 100,000 of 1,000,000 is 10%: behind. The
        // 900,000 missing over Nov 12, Dec 12 and Jan 12 is 300,000 a month.
        $this->bucket(saved: 100000);

        $this->assertSame(
            ['pace', true, 10.0, 300000.0, null, false],
            (fn (array $row) => [$row['basis'], $row['behind'], $row['percent'], $row['neededPerMonth'], $row['projectedOn'], $row['overdue']])($this->target()),
        );
    }

    public function test_a_bucket_ahead_of_the_pace_is_on_track(): void
    {
        // 400,000 is 40%, above the 30.8% the pace asks for today.
        $this->bucket(saved: 400000);

        $this->assertFalse($this->target()['behind']);
    }

    public function test_a_bucket_the_plan_fills_is_judged_by_when_the_forecast_holds_its_target(): void
    {
        // Fixed 500,000 from every income, target 1,000,000 by Jan 12. One income (Nov 5) gives 500,000: never there.
        $this->bucket(saved: 0)->update(['fill_rule' => FillRule::Fixed, 'fill_value' => 500000]);
        $this->income('2026-11-05', 3000000);

        $row = $this->target();

        $this->assertSame(['plan', true, null], [$row['basis'], $row['behind'], $row['projectedOn']]);
    }

    public function test_a_second_income_brings_the_plan_in_on_time(): void
    {
        // Incomes on Nov 5 and Dec 5 give 500,000 each: the bucket holds 1,000,000 on Dec 5, before Jan 12.
        $this->bucket(saved: 0)->update(['fill_rule' => FillRule::Fixed, 'fill_value' => 500000]);
        $this->income('2026-11-05', 3000000);
        $this->income('2026-12-05', 3000000);

        $row = $this->target();

        $this->assertSame(['plan', false, '2026-12-05'], [$row['basis'], $row['behind'], $row['projectedOn']]);
    }

    public function test_a_planned_income_split_makes_a_bucket_plan_driven_too(): void
    {
        // No fill rule, but the Nov 5 income puts 1,000,000 into it explicitly: held on Nov 5.
        $bucket = $this->bucket(saved: 0);
        PlannedEvent::factory()->for($this->user)->create([
            'type' => 'income', 'amount' => 3000000, 'currency' => 'COP', 'date' => '2026-11-05', 'certainty' => Certainty::Confirmed,
            'allocation_overrides' => [['bucket_id' => $bucket->id, 'amount' => 1000000]],
        ]);

        $row = $this->target();

        $this->assertSame(['plan', false, '2026-11-05'], [$row['basis'], $row['behind'], $row['projectedOn']]);
    }

    public function test_a_target_date_that_has_passed_is_overdue_and_behind_unless_reached(): void
    {
        $this->bucket(['name' => 'Late', 'target_date' => '2026-10-01'], saved: 600000);
        $this->bucket(['name' => 'Done', 'target_date' => '2026-10-01'], saved: 1000000);

        $this->assertSame([true, true], [$this->target('Late')['overdue'], $this->target('Late')['behind']]);
        $this->assertSame([true, false], [$this->target('Done')['overdue'], $this->target('Done')['behind']]);
    }

    public function test_buckets_without_a_target_date_or_amount_or_already_completed_are_left_out(): void
    {
        $this->bucket(['name' => 'No date', 'target_date' => null]);
        $this->bucket(['name' => 'No amount', 'cap_amount' => null]);
        $this->bucket(['name' => 'Completed', 'completed_on' => '2026-10-01']);
        $this->bucket(['name' => 'Kept']);

        $this->assertSame(['Kept'], array_column(app(SavingsTargets::class)->targets($this->user), 'name'));
    }

    public function test_it_only_reads_the_given_users_buckets_even_when_someone_else_is_logged_in(): void
    {
        $other = User::factory()->create();
        $this->bucket(['name' => 'Mine']);
        $this->bucket(['name' => 'Theirs'], owner: $other);

        $this->assertSame(['Theirs'], array_column(app(SavingsTargets::class)->targets($other), 'name'));
        $this->assertSame(['Mine'], array_column(app(SavingsTargets::class)->targets($this->user), 'name'));
    }
}
