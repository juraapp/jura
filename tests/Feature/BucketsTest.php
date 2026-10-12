<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BucketsTest extends TestCase
{
    use RefreshDatabase;

    private function spendingPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'kind' => 'spending',
            'category_id' => $category->id,
            'currency' => 'COP',
            'cap_amount' => 500000,
            'cycle' => 'monthly',
        ], $overrides);
    }

    public function test_index_lists_only_the_users_buckets_with_their_spending_progress(): void
    {
        Carbon::setTestNow('2026-10-12 09:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = Account::factory()->for($user)->create(['currency' => 'COP']);
        Bucket::factory()->for($user)->create(['category_id' => $category->id, 'cap_amount' => 1000, 'cycle' => 'monthly', 'currency' => 'COP']);
        Bucket::factory()->create();
        Transaction::factory()->for($user)->for($account)->create(['category_id' => $category->id, 'type' => 'expense', 'amount' => 850, 'date' => '2026-10-05']);
        Transaction::factory()->for($user)->for($account)->create(['category_id' => $category->id, 'type' => 'expense', 'amount' => 999, 'date' => '2026-09-05']);

        $this->actingAs($user)->get('/planning/buckets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buckets/Index')
                ->has('buckets', 1)
                ->where('buckets.0.spent', 850)
                ->where('buckets.0.remaining', 150)
                ->where('buckets.0.status', 'near_limit')
                ->where('buckets.0.tracksLimit', true)
            );
    }

    public function test_bucket_cards_project_planned_expenses_for_their_category_cycle_and_currency(): void
    {
        Carbon::setTestNow('2026-10-12 09:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $other = Category::factory()->expense()->create(['user_id' => $user->id]);
        Bucket::factory()->for($user)->create([
            'category_id' => $category->id, 'currency' => 'COP', 'cap_amount' => 1000, 'cycle' => 'monthly',
        ]);

        $plan = fn (array $attributes) => PlannedEvent::factory()->for($user)->expense()->create($attributes + [
            'category_id' => $category->id, 'currency' => 'COP', 'date' => '2026-10-20', 'amount' => 200,
        ]);
        $plan([]);                                                              // counts
        $plan(['amount' => 50, 'date' => '2026-10-31']);                        // counts
        $plan(['date' => '2026-11-02']);                                        // next cycle
        $plan(['status' => PlannedEventStatus::Skipped]);                       // closed
        $plan(['currency' => 'USD']);                                           // other currency
        $plan(['category_id' => $other->id]);                                   // other category

        $this->actingAs($user)->get('/planning/buckets')->assertInertia(fn (Assert $page) => $page
            ->has('buckets', 1)
            ->where('buckets.0.projected', 250)
            ->where('buckets.0.effectiveRemaining', 750)
        );
    }

    public function test_index_shows_the_funded_balance_and_recent_activity_of_a_bucket(): void
    {
        $user = User::factory()->create();
        $bucket = Bucket::factory()->savings()->for($user)->create(['currency' => 'COP']);
        Allocation::factory()->for($user)->for($bucket)->create(['amount' => 300, 'currency' => 'COP']);
        Allocation::factory()->for($user)->for($bucket)->create(['amount' => -100, 'currency' => 'COP']);

        $this->actingAs($user)->get('/planning/buckets')->assertInertia(fn (Assert $page) => $page
            ->where('buckets.0.balance', 200)
            ->where('buckets.0.available', 200)
            ->where('buckets.0.hasFunding', true)
            ->has('buckets.0.recentAllocations', 2)
        );
    }

    public function test_user_can_create_a_spending_bucket(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $this->actingAs($user)->post('/planning/buckets', $this->spendingPayload($category))
            ->assertRedirect()
            ->assertSessionHas('success');

        $bucket = Bucket::firstOrFail();
        $this->assertSame('Groceries', $bucket->name, 'defaults to the category name');
        $this->assertSame('500000.00', $bucket->cap_amount->toDecimalString());
        $this->assertSame(100, $bucket->priority);
        $this->assertSame(Carbon::today()->toDateString(), $bucket->tracking_started_on->toDateString());
    }

    public function test_user_can_create_a_savings_bucket_with_a_fill_rule_and_allowed_accounts(): void
    {
        $user = User::factory()->create();
        $cash = Account::factory()->for($user)->create();
        $other = Bucket::factory()->savings()->for($user)->create(['currency' => 'COP']);

        $this->actingAs($user)->post('/planning/buckets', [
            'kind' => 'savings',
            'name' => 'Car',
            'currency' => 'COP',
            'cap_amount' => 30000000,
            'priority' => 10,
            'fill_rule' => 'percent',
            'fill_value' => 15,
            'carry_over' => 'overflow',
            'overflow_bucket_id' => $other->id,
            'allowed_account_ids' => [$cash->id],
        ])->assertRedirect()->assertSessionHas('success');

        $bucket = Bucket::where('name', 'Car')->firstOrFail();
        $this->assertSame(FillRule::Percent, $bucket->fill_rule);
        $this->assertSame([$cash->id], $bucket->allowed_account_ids);
        $this->assertSame($other->id, $bucket->overflow_bucket_id);
        $this->assertSame(10, $bucket->priority);
        $this->assertNull($bucket->category_id);
    }

    public function test_creating_a_spending_bucket_for_an_existing_category_currency_and_cycle_updates_it(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $existing = Bucket::factory()->for($user)->create([
            'category_id' => $category->id, 'currency' => 'COP', 'cycle' => 'monthly', 'cap_amount' => 100000,
        ]);

        $this->actingAs($user)->post('/planning/buckets', $this->spendingPayload($category, ['cap_amount' => 750000]))
            ->assertRedirect();

        $this->assertDatabaseCount('buckets', 1);
        $this->assertSame('750000.00', $existing->fresh()->cap_amount->toDecimalString());
    }

    public function test_creating_a_bucket_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $foreignAccount = Account::factory()->create();

        $this->actingAs($user)->post('/planning/buckets', [
            'kind' => 'spending', 'category_id' => 999999, 'currency' => 'CO', 'cap_amount' => -5, 'cycle' => 'weekly',
        ])->assertSessionHasErrors(['category_id', 'currency', 'cap_amount', 'cycle']);

        $this->post('/planning/buckets', ['kind' => 'weird', 'currency' => 'COP'])->assertSessionHasErrors(['kind']);

        $this->post('/planning/buckets', ['kind' => 'savings', 'currency' => 'COP'])->assertSessionHasErrors(['name']);

        $this->post('/planning/buckets', [
            'kind' => 'savings', 'name' => 'X', 'currency' => 'COP', 'fill_rule' => 'percent', 'carry_over' => 'overflow',
            'allowed_account_ids' => [$foreignAccount->id],
        ])->assertSessionHasErrors(['fill_value', 'overflow_bucket_id', 'allowed_account_ids.0']);

        $this->post('/planning/buckets', [
            'kind' => 'savings', 'name' => 'X', 'currency' => 'COP', 'fill_rule' => 'fill_to_target',
        ])->assertSessionHasErrors(['cap_amount']);

        $this->post('/planning/buckets', [
            'kind' => 'savings', 'name' => 'X', 'currency' => 'COP', 'fill_rule' => 'percent', 'fill_value' => 150,
        ])->assertSessionHasErrors(['fill_value']);

        $this->assertDatabaseCount('buckets', 0);
    }

    public function test_the_overflow_bucket_must_be_the_users_own_and_in_the_same_currency(): void
    {
        $user = User::factory()->create();
        $foreign = Bucket::factory()->savings()->create(['currency' => 'COP']);
        $otherCurrency = Bucket::factory()->savings()->for($user)->create(['currency' => 'USD']);

        foreach ([$foreign->id, $otherCurrency->id] as $overflowId) {
            $this->actingAs($user)->post('/planning/buckets', [
                'kind' => 'savings', 'name' => 'X', 'currency' => 'COP', 'carry_over' => 'overflow', 'overflow_bucket_id' => $overflowId,
            ])->assertSessionHasErrors(['overflow_bucket_id']);
        }
    }

    public function test_user_can_update_their_bucket(): void
    {
        $user = User::factory()->create();
        $bucket = Bucket::factory()->for($user)->create();

        $this->actingAs($user)->patch("/planning/buckets/{$bucket->id}", $this->spendingPayload($bucket->category, [
            'currency' => $bucket->currency, 'cap_amount' => 999999, 'name' => 'Renamed', 'priority' => 5,
        ]))->assertRedirect();

        $bucket->refresh();
        $this->assertSame('999999.00', $bucket->cap_amount->toDecimalString());
        $this->assertSame('Renamed', $bucket->name);
        $this->assertSame(5, $bucket->priority);
    }

    public function test_an_update_cannot_collide_with_another_bucket_for_the_same_category_currency_and_cycle(): void
    {
        $user = User::factory()->create();
        $first = Bucket::factory()->for($user)->create(['currency' => 'COP', 'cycle' => 'monthly']);
        $second = Bucket::factory()->for($user)->create(['currency' => 'COP', 'cycle' => 'monthly']);

        $this->actingAs($user)->patch("/planning/buckets/{$second->id}", $this->spendingPayload($first->category))
            ->assertSessionHasErrors(['category_id']);
    }

    public function test_user_cannot_update_another_users_bucket(): void
    {
        $bucket = Bucket::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->patch("/planning/buckets/{$bucket->id}", $this->spendingPayload($bucket->category))
            ->assertNotFound();
    }

    public function test_user_can_delete_their_bucket(): void
    {
        $user = User::factory()->create();
        $bucket = Bucket::factory()->for($user)->create();

        $this->actingAs($user)->delete("/planning/buckets/{$bucket->id}")->assertRedirect();

        $this->assertDatabaseMissing('buckets', ['id' => $bucket->id]);
    }

    public function test_user_cannot_delete_another_users_bucket(): void
    {
        $bucket = Bucket::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->delete("/planning/buckets/{$bucket->id}")->assertNotFound();

        $this->assertDatabaseHas('buckets', ['id' => $bucket->id]);
    }

    public function test_deleting_a_funded_bucket_returns_its_money_to_the_default_bucket(): void
    {
        $user = User::factory()->create();
        $default = Bucket::factory()->savings()->for($user)->create(['currency' => 'COP', 'is_default' => true]);
        $bucket = Bucket::factory()->savings()->for($user)->create(['currency' => 'COP']);
        Allocation::factory()->for($user)->for($bucket)->create(['amount' => 400, 'currency' => 'COP']);

        $this->actingAs($user)->delete("/planning/buckets/{$bucket->id}")->assertRedirect();

        $this->assertEquals(400, Allocation::where("bucket_id", $default->id)->sum("amount"));
        $this->assertDatabaseMissing('buckets', ['id' => $bucket->id]);
    }

    public function test_only_one_bucket_per_currency_can_be_the_default(): void
    {
        $user = User::factory()->create();
        $first = Bucket::factory()->savings()->for($user)->create(['currency' => 'COP', 'is_default' => true]);
        $usd = Bucket::factory()->savings()->for($user)->create(['currency' => 'USD', 'is_default' => true]);

        $this->actingAs($user)->post('/planning/buckets', [
            'kind' => 'savings', 'name' => 'New default', 'currency' => 'COP', 'is_default' => true,
        ])->assertRedirect();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($usd->fresh()->is_default);
        $this->assertTrue(Bucket::where('name', 'New default')->firstOrFail()->is_default);
    }

    public function test_the_old_budgets_url_redirects_to_buckets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/planning/budgets')->assertRedirect('/planning/buckets');
    }
}
