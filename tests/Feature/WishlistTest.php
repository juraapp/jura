<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Road bike', 'price' => 2500000, 'currency' => 'COP', 'importance' => 4], $overrides);
    }

    /* ---------- index ---------- */

    public function test_index_lists_only_the_users_projects_and_items_with_funding_progress(): void
    {
        $project = Project::factory()->for($this->user)->create(['name' => 'Cycling']);
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Bike fund']);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => 1000000, 'currency' => 'COP']);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'bucket_id' => $bucket->id, 'price' => 2000000, 'currency' => 'COP']);
        WishlistItem::factory()->for($this->user)->create();
        WishlistItem::factory()->create();
        Project::factory()->create();

        $this->get('/planning/wishlist')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wishlist/Index')
            ->has('projects', 1)
            ->has('items', 2)
            ->where('items.0.fundedPercent', 50)
            ->where('items.0.bucket.name', 'Bike fund')
        );
    }

    /* ---------- projects ---------- */

    public function test_user_can_create_update_and_delete_a_project_and_its_items_become_unassigned(): void
    {
        $this->post('/planning/wishlist/projects', ['name' => 'Home studio', 'kind' => 'project', 'color' => '#2563eb'])
            ->assertRedirect()->assertSessionHas('success');
        $project = Project::firstOrFail();

        $this->patch("/planning/wishlist/projects/{$project->id}", ['name' => 'Music corner', 'kind' => 'hobby'])->assertRedirect();
        $this->assertSame('Music corner', $project->fresh()->name);

        $item = WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id]);
        $this->delete("/planning/wishlist/projects/{$project->id}")->assertRedirect();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertNull($item->fresh()->project_id);
    }

    public function test_project_validation_and_isolation(): void
    {
        $this->post('/planning/wishlist/projects', ['name' => '', 'kind' => 'weird'])->assertSessionHasErrors(['name', 'kind']);

        $foreign = Project::factory()->create();
        $this->patch("/planning/wishlist/projects/{$foreign->id}", ['name' => 'x', 'kind' => 'hobby'])->assertNotFound();
        $this->delete("/planning/wishlist/projects/{$foreign->id}")->assertNotFound();
    }

    /* ---------- items ---------- */

    public function test_user_can_create_an_item_with_a_project_bucket_and_allowed_accounts(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        $cash = Account::factory()->for($this->user)->create(['currency' => 'COP']);

        $this->post('/planning/wishlist', $this->itemPayload([
            'project_id' => $project->id, 'bucket_id' => $bucket->id, 'allowed_account_ids' => [$cash->id],
            'desired_by' => '2027-03-01', 'url' => 'https://example.com/bike', 'notes' => 'Carbon frame',
        ]))->assertRedirect()->assertSessionHas('success');

        $item = WishlistItem::firstOrFail();
        $this->assertSame('2500000.00', $item->price->toDecimalString());
        $this->assertSame(WishlistStatus::Wanted, $item->status);
        $this->assertSame(4, $item->importance);
        $this->assertSame([$cash->id], $item->allowed_account_ids);
        $this->assertSame('2027-03-01', $item->desired_by->toDateString());
    }

    public function test_item_validation(): void
    {
        $foreignBucket = Bucket::factory()->savings()->create(['currency' => 'COP']);
        $spending = Bucket::factory()->for($this->user)->create(['currency' => 'COP']);
        $foreignAccount = Account::factory()->create();
        $foreignProject = Project::factory()->create();

        $this->post('/planning/wishlist', ['name' => '', 'price' => 0, 'importance' => 9, 'currency' => 'X'])
            ->assertSessionHasErrors(['name', 'price', 'importance', 'currency']);

        foreach ([$foreignBucket->id, $spending->id] as $bucketId) {
            $this->post('/planning/wishlist', $this->itemPayload(['bucket_id' => $bucketId]))->assertSessionHasErrors('bucket_id');
        }

        $this->post('/planning/wishlist', $this->itemPayload(['allowed_account_ids' => [$foreignAccount->id]]))
            ->assertSessionHasErrors('allowed_account_ids.0');
        $this->post('/planning/wishlist', $this->itemPayload(['project_id' => $foreignProject->id]))->assertSessionHasErrors('project_id');

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_user_can_update_drop_restore_and_delete_an_item(): void
    {
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP']);

        $this->patch("/planning/wishlist/{$item->id}", $this->itemPayload(['name' => 'Gravel bike', 'status' => 'dropped']))->assertRedirect();
        $this->assertSame('Gravel bike', $item->fresh()->name);
        $this->assertSame(WishlistStatus::Dropped, $item->fresh()->status);

        $this->patch("/planning/wishlist/{$item->id}", $this->itemPayload(['status' => 'wanted']))->assertRedirect();
        $this->assertSame(WishlistStatus::Wanted, $item->fresh()->status);

        $this->delete("/planning/wishlist/{$item->id}")->assertRedirect();
        $this->assertDatabaseMissing('wishlist_items', ['id' => $item->id]);
    }

    public function test_other_users_items_are_hidden(): void
    {
        $item = WishlistItem::factory()->create();

        $this->patch("/planning/wishlist/{$item->id}", $this->itemPayload())->assertNotFound();
        $this->delete("/planning/wishlist/{$item->id}")->assertNotFound();
        $this->post("/planning/wishlist/{$item->id}/purchase", [])->assertNotFound();
        $this->post("/planning/wishlist/{$item->id}/unpurchase")->assertNotFound();
    }

    /* ---------- purchasing ---------- */

    /**
     * @return array{0: WishlistItem, 1: Account, 2: Category, 3: Bucket}
     */
    private function purchasableItem(float $funded = 3000000, array $itemOverrides = [], array $bucketOverrides = []): array
    {
        $account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 10000000]);
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $bucket = Bucket::factory()->savings()->for($this->user)->create(array_merge(['currency' => 'COP'], $bucketOverrides));
        if ($funded > 0) {
            Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => $funded, 'currency' => 'COP']);
        }
        $item = WishlistItem::factory()->for($this->user)->create(array_merge(
            ['bucket_id' => $bucket->id, 'price' => 2500000, 'currency' => 'COP', 'name' => 'Road bike'],
            $itemOverrides,
        ));

        return [$item, $account, $category, $bucket];
    }

    public function test_marking_purchased_records_the_expense_and_spends_from_the_bucket(): void
    {
        [$item, $account, $category, $bucket] = $this->purchasableItem();

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $account->id, 'category_id' => $category->id])
            ->assertRedirect()->assertSessionHas('success');

        $transaction = Transaction::firstOrFail();
        $this->assertSame('expense', $transaction->type->value);
        $this->assertSame('2500000.00', $transaction->amount->toDecimalString());
        $this->assertSame('Road bike', $transaction->description);
        $this->assertSame('2026-10-12', $transaction->date->toDateString());

        $item->refresh();
        $this->assertSame(WishlistStatus::Purchased, $item->status);
        $this->assertSame($transaction->id, $item->purchased_transaction_id);
        $this->assertSame('2026-10-12', $item->purchased_on->toDateString());

        $spend = Allocation::where('source', AllocationSource::Spend->value)->firstOrFail();
        $this->assertSame($bucket->id, $spend->bucket_id);
        $this->assertSame('-2500000.00', $spend->amount->toDecimalString());
        $this->assertSame($transaction->id, $spend->source_id);
        $this->assertSame($spend->id, $item->purchase_allocation_id);
    }

    public function test_the_bucket_only_pays_what_it_has_and_the_purchase_still_happens(): void
    {
        [$item, $account, $category, $bucket] = $this->purchasableItem(funded: 1000000);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $account->id, 'category_id' => $category->id])->assertRedirect();

        $this->assertSame('2500000.00', Transaction::firstOrFail()->amount->toDecimalString());
        $this->assertSame(0.0, (float) Allocation::where('bucket_id', $bucket->id)->sum('amount'));
    }

    public function test_an_item_without_a_bucket_is_purchased_without_a_spend_allocation(): void
    {
        $account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'bucket_id' => null]);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $account->id, 'category_id' => $category->id, 'amount' => 123, 'date' => '2026-10-01'])
            ->assertRedirect();

        $this->assertDatabaseCount('allocations', 0);
        $this->assertSame('123.00', Transaction::firstOrFail()->amount->toDecimalString());
        $this->assertSame('2026-10-01', $item->fresh()->purchased_on->toDateString());
    }

    public function test_purchases_respect_account_restrictions_of_the_item_and_its_bucket_and_the_currency(): void
    {
        $physical = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $card = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $usd = Account::factory()->for($this->user)->create(['currency' => 'USD']);
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        [$item] = $this->purchasableItem(itemOverrides: ['allowed_account_ids' => [$physical->id]], bucketOverrides: ['allowed_account_ids' => [$physical->id, $card->id]]);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $card->id, 'category_id' => $category->id])
            ->assertSessionHasErrors('account_id');
        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $usd->id, 'category_id' => $category->id])
            ->assertSessionHasErrors('account_id');

        $item->update(['allowed_account_ids' => null]);
        $item->bucket->update(['allowed_account_ids' => [$physical->id]]);
        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $card->id, 'category_id' => $category->id])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('transactions', 0);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $physical->id, 'category_id' => $category->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_purchase_requires_an_account_and_category_and_cannot_be_repeated(): void
    {
        [$item, $account, $category] = $this->purchasableItem();

        $this->post("/planning/wishlist/{$item->id}/purchase", [])->assertSessionHasErrors(['account_id', 'category_id']);

        $payload = ['account_id' => $account->id, 'category_id' => $category->id];
        $this->post("/planning/wishlist/{$item->id}/purchase", $payload)->assertRedirect();
        $this->post("/planning/wishlist/{$item->id}/purchase", $payload)->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_undoing_a_purchase_removes_the_transaction_and_returns_the_money_to_the_bucket(): void
    {
        [$item, $account, $category, $bucket] = $this->purchasableItem();
        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $account->id, 'category_id' => $category->id]);

        $this->post("/planning/wishlist/{$item->id}/unpurchase")->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(3000000.0, (float) Allocation::where('bucket_id', $bucket->id)->sum('amount'));
        $item->refresh();
        $this->assertSame(WishlistStatus::Wanted, $item->status);
        $this->assertNull($item->purchased_transaction_id);
        $this->assertNull($item->purchase_allocation_id);
    }

    public function test_only_purchased_items_can_be_unpurchased(): void
    {
        $item = WishlistItem::factory()->for($this->user)->create();

        $this->post("/planning/wishlist/{$item->id}/unpurchase")->assertRedirect()->assertSessionHas('error');
    }
}
