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
        Bucket::factory()->savings()->for($this->user)->asDefault()->create(['name' => 'Unallocated', 'currency' => 'COP']);

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
