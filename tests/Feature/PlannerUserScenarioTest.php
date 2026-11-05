<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The case that started the free-money planner: wish-list items without a bucket used to never be affordable because
 * all income was counted only through buckets. Every date below is worked out by hand.
 */
class PlannerUserScenarioTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WishlistItem $laptop;

    private WishlistItem $motorbike;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-30 12:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);

        $account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);
        $goals = Bucket::factory()->savings()->for($this->user)->create([
            'name' => 'Goals', 'currency' => 'COP', 'cap_amount' => 3000000, 'fill_rule' => FillRule::PercentOfRemaining,
            'fill_value' => 100, 'allowed_account_ids' => [$account->id],
        ]);
        app(BucketLedger::class)->setOpeningBalance($goals, Money::of(1000000));

        PlannedEvent::factory()->for($this->user)->income()->create([
            'amount' => 3000000, 'currency' => 'COP', 'date' => '2026-10-30', 'account_id' => $account->id, 'certainty' => 'confirmed',
        ]);
        PlannedEventSeries::factory()->for($this->user)->create([
            'type' => 'income', 'amount' => 3000000, 'currency' => 'COP', 'account_id' => $account->id, 'certainty' => 'expected',
            'frequency' => RecurringFrequency::Monthly, 'starts_on' => '2026-11-30',
        ]);

        $this->laptop = WishlistItem::factory()->for($this->user)->create(['name' => 'Laptop', 'price' => 3999999, 'importance' => 4, 'currency' => 'COP', 'bucket_id' => null]);
        $this->motorbike = WishlistItem::factory()->for($this->user)->create(['name' => 'Motorbike', 'price' => 5000000, 'importance' => 3, 'currency' => 'COP', 'bucket_id' => null]);
    }

    public function test_both_items_find_a_date_and_the_planner_says_which_incomes_it_counts(): void
    {
        // Today: 1,000,000 in the account, all of it in Goals (opening), so nothing is ready to assign.
        // Oct 30 income 3,000,000: Goals takes what is left of its 3,000,000 cap (2,000,000), 1,000,000 stays free.
        // Nov 30: Goals is full, all 3,000,000 stays free: 4,000,000 free, enough for the 3,999,999 laptop (1 left).
        // Dec 30: 3,000,001 free. Jan 30: 6,000,001 free, enough for the 5,000,000 motorbike.
        $suggestions = $this->postJson('/planning/planner/suggest', [
            'currency' => 'COP', 'view' => 'expected', 'horizon_months' => 12, 'item_ids' => [$this->motorbike->id, $this->laptop->id],
        ])->assertOk()->json('suggestions');

        $best = $suggestions[0];
        $this->assertSame([$this->laptop->id, $this->motorbike->id], $best['order']);
        $this->assertSame(['2026-11-30', '2027-01-30'], array_column($best['lines'], 'purchaseDate'));
        $this->assertEquals([0, 0], array_column($best['lines'], 'fromBucket'));
        $this->assertEquals([3999999, 5000000], array_column($best['lines'], 'fromFree'));
        $this->assertEquals(0, $best['readyToAssign']['start']);

        // One confirmed income today plus the 12 expected monthly ones up to Oct 30, 2027: 13 of 13, 39,000,000.
        $this->assertSame([13, 13], [$best['incomes']['counted'], $best['incomes']['total']]);
        $this->assertEquals(39000000, $best['incomes']['countedAmount']);

        // The motorbike first: Dec 30 has 7,000,000 free (1,000,000 + 3,000,000 + 3,000,000), then the laptop waits
        // for Jan 30 (2,000,000 + 3,000,000).
        $built = $this->postJson('/planning/planner/simulate', [
            'currency' => 'COP', 'view' => 'expected', 'horizon_months' => 12,
            'lines' => [['item_id' => $this->motorbike->id], ['item_id' => $this->laptop->id]],
        ])->assertOk()->json();
        $this->assertSame(['2026-12-30', '2027-01-30'], array_column($built['lines'], 'purchaseDate'));
    }

    public function test_the_conservative_view_counts_only_the_confirmed_income(): void
    {
        $result = $this->postJson('/planning/planner/simulate', [
            'currency' => 'COP', 'view' => 'conservative', 'horizon_months' => 12, 'lines' => [['item_id' => $this->laptop->id]],
        ])->assertOk()->json();

        // Only today's 3,000,000 counts: 1,000,000 free, 2,999,999 short of the laptop.
        $this->assertSame([1, 13], [$result['incomes']['counted'], $result['incomes']['total']]);
        $this->assertNull($result['lines'][0]['purchaseDate']);
        $this->assertSame('free', $result['lines'][0]['limitedBy']);
        $this->assertEquals(2999999, $result['lines'][0]['shortfall']);
    }
}
