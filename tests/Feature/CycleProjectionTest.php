<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Category;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Services\CycleProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CycleProjectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $groceryCategory;

    private Bucket $groceries;

    private Bucket $savings;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-01 08:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->groceryCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $this->savings = Bucket::factory()->savings()->for($this->user)->create([
            'name' => 'Emergency', 'currency' => 'COP', 'priority' => 10, 'cap_amount' => 1000,
            'fill_rule' => FillRule::FillToTarget, 'tracking_started_on' => '2026-10-01',
        ]);
        $this->groceries = Bucket::factory()->for($this->user)->create([
            'name' => 'Groceries', 'currency' => 'COP', 'priority' => 0, 'category_id' => $this->groceryCategory->id,
            'cap_amount' => 500, 'cycle' => 'monthly', 'fill_rule' => FillRule::FillToTarget,
            'carry_over' => CarryOverPolicy::Overflow, 'overflow_bucket_id' => $this->savings->id,
            'tracking_started_on' => '2026-10-01',
        ]);

        PlannedEvent::factory()->for($this->user)->create([
            'type' => 'income', 'amount' => 1000, 'currency' => 'COP', 'date' => '2026-11-01', 'certainty' => 'confirmed',
        ]);
    }

    private function projection(): array
    {
        return app(CycleProjector::class)->project($this->user, 'COP');
    }

    public function test_the_start_of_the_month_shows_groceries_and_savings_filled_provisionally(): void
    {
        $projection = $this->projection();

        $this->assertSame('500.00', $projection[$this->groceries->id]['provisional']->toDecimalString());
        $this->assertSame('500.00', $projection[$this->savings->id]['provisional']->toDecimalString());
        $this->assertSame(0, Allocation::count(), 'a projection never writes allocations');
    }

    public function test_unspent_groceries_are_expected_to_overflow_into_savings_at_the_end_of_the_cycle(): void
    {
        $projection = $this->projection();

        $this->assertSame('2026-11-30', $projection[$this->groceries->id]['end']->toDateString());
        $this->assertSame('500.00', $projection[$this->groceries->id]['projectedRelease']->toDecimalString());
        $this->assertSame('500.00', $projection[$this->savings->id]['projectedIn']->toDecimalString());
        $this->assertSame('1000.00', $projection[$this->savings->id]['projectedClose']->toDecimalString());
        $this->assertSame('0.00', $projection[$this->groceries->id]['projectedClose']->toDecimalString());
    }

    public function test_planned_grocery_spending_reduces_the_expected_overflow(): void
    {
        PlannedEvent::factory()->for($this->user)->create([
            'type' => 'expense', 'amount' => 400, 'currency' => 'COP', 'date' => '2026-11-15', 'category_id' => $this->groceryCategory->id, 'certainty' => 'confirmed',
        ]);

        $projection = $this->projection();

        $this->assertSame('100.00', $projection[$this->groceries->id]['projectedRelease']->toDecimalString());
        $this->assertSame('600.00', $projection[$this->savings->id]['projectedClose']->toDecimalString());
    }

    public function test_income_that_is_only_expected_is_ignored_by_the_conservative_view(): void
    {
        PlannedEvent::query()->update(['certainty' => 'speculative']);

        $this->assertSame('0.00', $this->projection()[$this->groceries->id]['provisional']->toDecimalString());
    }

    public function test_a_realized_income_leaves_the_provisional_pool(): void
    {
        Allocation::factory()->for($this->user)->for($this->groceries)->create(['amount' => 500, 'currency' => 'COP', 'date' => '2026-11-01']);
        Allocation::factory()->for($this->user)->for($this->savings)->create(['amount' => 500, 'currency' => 'COP', 'date' => '2026-11-01']);
        PlannedEvent::query()->update(['status' => 'realized']);

        $projection = $this->projection();

        $this->assertSame('0.00', $projection[$this->groceries->id]['provisional']->toDecimalString());
        $this->assertSame('0.00', $projection[$this->savings->id]['provisional']->toDecimalString());
        $this->assertSame('500.00', $projection[$this->groceries->id]['projectedRelease']->toDecimalString(), 'real money still overflows at the end');
    }
}
