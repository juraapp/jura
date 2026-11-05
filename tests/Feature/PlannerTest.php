<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\Scenario;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bucket $bikeFund;

    private WishlistItem $bike;

    private WishlistItem $helmet;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);

        $this->bikeFund = Bucket::factory()->savings()->for($this->user)->priority(10)->rule(FillRule::Fixed, 500000)
            ->create(['name' => 'Bike fund', 'currency' => 'COP']);

        $this->bike = WishlistItem::factory()->for($this->user)->create([
            'name' => 'Road bike', 'price' => 1000000, 'currency' => 'COP', 'importance' => 5, 'bucket_id' => $this->bikeFund->id,
        ]);
        $this->helmet = WishlistItem::factory()->for($this->user)->create([
            'name' => 'Helmet', 'price' => 200000, 'currency' => 'COP', 'importance' => 2, 'bucket_id' => $this->bikeFund->id,
        ]);

        $this->plan(500000, '2026-11-01', Certainty::Expected);
        $this->plan(500000, '2026-12-01', Certainty::Confirmed);
    }

    private function plan(int $amount, string $date, Certainty $certainty, array $overrides = []): PlannedEvent
    {
        return PlannedEvent::factory()->for($this->user)->income()->create(array_merge([
            'amount' => $amount, 'currency' => 'COP', 'date' => $date, 'certainty' => $certainty,
        ], $overrides));
    }

    private function simulate(array $overrides = []): array
    {
        return $this->postJson('/planning/planner/simulate', array_merge([
            'currency' => 'COP', 'view' => 'expected', 'horizon_months' => 12,
            'lines' => [['item_id' => $this->bike->id]],
        ], $overrides))->assertOk()->json();
    }

    /* ---------- page ---------- */

    public function test_index_lists_wanted_items_and_saved_scenarios_for_the_user_only(): void
    {
        WishlistItem::factory()->for($this->user)->create(['status' => 'purchased']);
        WishlistItem::factory()->create();
        $scenario = Scenario::factory()->for($this->user)->create(['name' => 'Bike first']);
        $scenario->lines()->create(['wishlist_item_id' => $this->bike->id, 'position' => 1]);
        Scenario::factory()->create();

        $this->get('/planning/planner')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Planner/Index')
            ->has('items', 2)
            ->has('scenarios', 1)
            ->where('scenarios.0.name', 'Bike first')
            ->where('scenarios.0.lines.0.itemId', $this->bike->id)
            ->where('hasFillRules', true)
            ->where('defaultView', 'expected')
        );
    }

    public function test_scenario_lines_say_which_items_are_no_longer_wanted(): void
    {
        $bought = WishlistItem::factory()->for($this->user)->create(['name' => 'Old phone', 'currency' => 'COP', 'status' => 'purchased']);
        $scenario = Scenario::factory()->for($this->user)->create();
        $scenario->lines()->create(['wishlist_item_id' => $this->bike->id, 'position' => 1]);
        $scenario->lines()->create(['wishlist_item_id' => $bought->id, 'position' => 2]);

        $this->get('/planning/planner')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('scenarios.0.lines.0.wanted', true)
            ->where('scenarios.0.lines.1.name', 'Old phone')
            ->where('scenarios.0.lines.1.wanted', false)
        );
    }

    /* ---------- simulate ---------- */

    public function test_simulating_returns_the_purchase_date_for_the_chosen_view(): void
    {
        $expected = $this->simulate(['view' => 'expected']);

        $this->assertSame('2026-12-01', $expected['lines'][0]['purchaseDate']);
        $this->assertSame(50, $expected['lines'][0]['delayDays']);
        $this->assertSame($this->bike->id, $expected['lines'][0]['itemId']);
        $this->assertSame(0.0, (float) $expected['lines'][0]['shortfall']);
        $this->assertSame('Bike fund', collect($expected['buckets'])->firstWhere('id', $this->bikeFund->id)['name']);
        $this->assertEquals(['start' => 0, 'end' => 0], $expected['readyToAssign']);
    }

    public function test_the_conservative_view_ignores_income_that_is_only_expected(): void
    {
        $conservative = $this->simulate(['view' => 'conservative']);

        $this->assertNull($conservative['lines'][0]['purchaseDate'], 'only 500,000 is confirmed, so the 1,000,000 bike is out of reach');
        $this->assertEquals(500000, $conservative['lines'][0]['shortfall']);

        $optimistic = $this->simulate(['view' => 'optimistic']);
        $this->assertSame('2026-12-01', $optimistic['lines'][0]['purchaseDate']);
    }

    public function test_existing_bucket_balances_count_and_order_and_not_before_are_respected(): void
    {
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 300000]);
        Allocation::factory()->for($this->user)->for($this->bikeFund)->create(['amount' => 300000, 'currency' => 'COP']);

        $result = $this->simulate([
            'lines' => [['item_id' => $this->helmet->id, 'not_before' => '2026-10-20'], ['item_id' => $this->bike->id]],
        ]);

        $this->assertSame('2026-10-20', $result['lines'][0]['purchaseDate'], '300,000 is already there for the 200,000 helmet');
        $this->assertSame('2026-12-01', $result['lines'][1]['purchaseDate']);
    }

    public function test_planned_events_that_are_closed_or_in_another_currency_do_not_count(): void
    {
        PlannedEvent::query()->delete();
        $this->plan(500000, '2026-11-01', Certainty::Confirmed, ['status' => 'skipped']);
        $this->plan(500000, '2026-11-02', Certainty::Confirmed, ['currency' => 'USD']);

        $this->assertNull($this->simulate(['lines' => [['item_id' => $this->helmet->id]]])['lines'][0]['purchaseDate']);
    }

    public function test_an_item_whose_accounts_do_not_overlap_its_buckets_is_blocked(): void
    {
        $cash = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $card = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $this->bikeFund->update(['allowed_account_ids' => [$card->id]]);
        $this->bike->update(['allowed_account_ids' => [$cash->id]]);

        $result = $this->simulate();

        $this->assertSame('accounts', $result['lines'][0]['blocked']);
        $this->assertNull($result['lines'][0]['purchaseDate']);
    }

    public function test_the_result_says_which_incomes_count_and_how_each_item_is_paid(): void
    {
        $brokerage = Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP', 'initial_balance' => 0]);
        $this->plan(300000, '2026-11-15', Certainty::Confirmed, ['account_id' => $brokerage->id]);
        $this->plan(100000, '2026-11-20', Certainty::Speculative);
        $this->plan(50000, '2026-10-01', Certainty::Expected);

        $result = $this->simulate();

        // Counted: Oct 1 (overdue, so today) 50,000 + Nov 1 + Dec 1 = 1,050,000 of the three expected or confirmed incomes
        // into the budget; the speculative one is not counted, the brokerage one is off budget.
        $this->assertEquals([
            'view' => 'expected', 'counted' => 3, 'total' => 4, 'countedAmount' => 1050000, 'totalAmount' => 1150000,
            'overdue' => 1, 'offBudget' => 1, 'offBudgetAmount' => 300000, 'allocated' => 1050000, 'leftFree' => 0,
        ], $result['incomes']);
        // The fund has no cycle, so its fixed 500,000 applies to each income: 50,000 today, 500,000 on Nov 1 (550,000,
        // short of the bike) and 500,000 on Dec 1 (1,050,000): it pays the whole bike then.
        $this->assertSame('2026-12-01', $result['lines'][0]['purchaseDate']);
        $this->assertEquals([1000000, 0], [$result['lines'][0]['fromBucket'], $result['lines'][0]['fromFree']]);
        $this->assertSame([], $result['lines'][0]['warnings']);
    }

    public function test_a_bucket_capped_below_the_price_is_flagged_and_ready_to_assign_pays_the_rest(): void
    {
        $this->bikeFund->update(['cap_amount' => 600000]);

        $result = $this->simulate();

        // Nov 1: the fund takes 500,000 (room 600,000). Dec 1: it takes the 100,000 left of its room, 400,000 stays free.
        $line = $result['lines'][0];
        $this->assertSame(['cap_below_price'], $line['warnings']);
        $this->assertSame('2026-12-01', $line['purchaseDate']);
        $this->assertEquals([600000, 400000], [$line['fromBucket'], $line['fromFree']]);
        $this->assertEquals(['start' => 0, 'end' => 0], $result['readyToAssign']);
    }

    public function test_an_item_waiting_for_ready_to_assign_says_why_and_how_much_is_missing(): void
    {
        PlannedEvent::query()->delete();
        $checking = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);
        PlannedEvent::factory()->for($this->user)->expense()->create(['amount' => 800000, 'currency' => 'COP', 'date' => '2026-10-20', 'account_id' => $checking->id]);
        $desk = WishlistItem::factory()->for($this->user)->create(['name' => 'Desk', 'price' => 900000, 'currency' => 'COP', 'bucket_id' => null]);

        $result = $this->simulate(['lines' => [['item_id' => $desk->id]]]);

        // 1,000,000 today, but 800,000 leaves on Oct 20: 200,000 is all that stays.
        $line = $result['lines'][0];
        $this->assertNull($line['purchaseDate']);
        $this->assertSame('free', $line['limitedBy']);
        $this->assertEquals(700000, $line['shortfall']);
        $this->assertEquals(['start' => 1000000, 'end' => 200000], $result['readyToAssign']);
        $this->assertEquals(['date' => '2026-10-31', 'cash' => 200000, 'free' => 200000], $result['timeline'][0]);
    }

    public function test_an_item_only_off_budget_accounts_can_pay_for_is_blocked(): void
    {
        $brokerage = Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP']);
        $this->bike->update(['allowed_account_ids' => [$brokerage->id]]);

        $this->assertSame('off_budget', $this->simulate()['lines'][0]['blocked']);
    }

    public function test_simulation_validates_its_input(): void
    {
        $foreign = WishlistItem::factory()->create(['currency' => 'COP']);
        $purchased = WishlistItem::factory()->for($this->user)->create(['status' => 'purchased', 'currency' => 'COP']);
        $usd = WishlistItem::factory()->for($this->user)->create(['currency' => 'USD']);

        $this->postJson('/planning/planner/simulate', ['currency' => 'X', 'view' => 'wild', 'horizon_months' => 1, 'lines' => []])
            ->assertJsonValidationErrors(['currency', 'view', 'horizon_months', 'lines']);

        foreach ([$foreign, $purchased, $usd] as $item) {
            $this->postJson('/planning/planner/simulate', [
                'currency' => 'COP', 'view' => 'expected', 'horizon_months' => 12, 'lines' => [['item_id' => $item->id]],
            ])->assertJsonValidationErrors('lines.0.item_id');
        }
    }

    /* ---------- suggest ---------- */

    public function test_suggestions_put_the_item_you_want_most_first_when_the_money_allows(): void
    {
        $response = $this->postJson('/planning/planner/suggest', [
            'currency' => 'COP', 'view' => 'optimistic', 'horizon_months' => 12,
            'item_ids' => [$this->helmet->id, $this->bike->id],
        ])->assertOk()->json();

        $this->assertNotEmpty($response['suggestions']);
        $best = $response['suggestions'][0];
        $this->assertSame('best', $best['strategy']);
        $this->assertSame('Best overall', $best['label']);
        $this->assertCount(2, $best['order']);
        $this->assertCount(2, $best['lines']);
        $this->assertArrayHasKey('score', $best);
    }

    public function test_suggesting_requires_at_least_one_valid_item(): void
    {
        $this->postJson('/planning/planner/suggest', ['currency' => 'COP', 'view' => 'expected', 'horizon_months' => 12, 'item_ids' => []])
            ->assertJsonValidationErrors('item_ids');
    }

    /* ---------- scenarios ---------- */

    public function test_user_can_save_and_delete_a_scenario(): void
    {
        $this->post('/planning/planner', [
            'name' => 'Helmet then bike', 'currency' => 'COP', 'view' => 'expected', 'horizon_months' => 24,
            'lines' => [['item_id' => $this->helmet->id, 'not_before' => '2026-11-15'], ['item_id' => $this->bike->id]],
        ])->assertRedirect()->assertSessionHas('success');

        $scenario = Scenario::firstOrFail();
        $this->assertSame('Helmet then bike', $scenario->name);
        $this->assertSame(24, $scenario->horizon_months);
        $this->assertSame([$this->helmet->id, $this->bike->id], $scenario->lines()->orderBy('position')->pluck('wishlist_item_id')->all());
        $this->assertSame('2026-11-15', $scenario->lines()->orderBy('position')->first()->not_before->toDateString());

        $this->delete("/planning/planner/{$scenario->id}")->assertRedirect();
        $this->assertDatabaseMissing('scenarios', ['id' => $scenario->id]);
        $this->assertDatabaseCount('scenario_lines', 0);
    }

    public function test_saving_requires_a_name_and_valid_lines_and_other_users_scenarios_are_untouchable(): void
    {
        $this->post('/planning/planner', ['name' => '', 'currency' => 'COP', 'view' => 'expected', 'horizon_months' => 12, 'lines' => []])
            ->assertSessionHasErrors(['name', 'lines']);

        $foreign = Scenario::factory()->create();
        $this->delete("/planning/planner/{$foreign->id}")->assertNotFound();
    }

    public function test_guests_are_redirected(): void
    {
        auth()->logout();

        $this->get('/planning/planner')->assertRedirect('/login');
    }
}
