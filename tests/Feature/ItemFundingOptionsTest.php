<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Saving up for a wish-list item in a bucket made on the spot, and making a project from an item's form.
 */
class ItemFundingOptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);
        $this->actingAs($this->user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Camera', 'price' => '2500000', 'currency' => 'COP', 'importance' => 4], $overrides);
    }

    public function test_an_item_can_get_a_bucket_filled_a_fixed_amount_every_month(): void
    {
        $this->post('/planning/wishlist', $this->payload([
            'new_bucket' => true, 'new_bucket_fill_rule' => 'fixed', 'new_bucket_fill_value' => '400000', 'allowed_account_ids' => [$this->account->id],
        ]))->assertSessionHasNoErrors();

        $item = WishlistItem::sole();
        $bucket = Bucket::sole();
        $this->assertSame($bucket->id, $item->bucket_id);
        $this->assertSame(
            ['Camera', 'savings', 'COP', '2500000.00', 'fixed', '400000.0000', 'monthly', true, [$this->account->id]],
            [$bucket->name, $bucket->kind->value, $bucket->currency, $bucket->cap_amount->toDecimalString(), $bucket->fill_rule->value,
                (string) $bucket->fill_value, $bucket->cycle->value, $bucket->completes_on_purchase, $bucket->allowed_account_ids],
        );
    }

    public function test_a_percentage_bucket_has_no_monthly_cycle(): void
    {
        $this->post('/planning/wishlist', $this->payload(['new_bucket' => true, 'new_bucket_fill_rule' => 'percent', 'new_bucket_fill_value' => '20']))
            ->assertSessionHasNoErrors();

        $bucket = Bucket::sole();
        $this->assertSame(['percent', '20.0000', null], [$bucket->fill_rule->value, (string) $bucket->fill_value, $bucket->cycle?->value]);
    }

    public function test_editing_an_item_can_replace_its_bucket_with_a_new_one(): void
    {
        $old = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $old->id]);

        $this->patch("/planning/wishlist/{$item->id}", $this->payload(['price' => '900', 'new_bucket' => true, 'new_bucket_fill_rule' => 'of_remaining', 'new_bucket_fill_value' => '50']))
            ->assertSessionHasNoErrors();

        $new = Bucket::where('name', 'Camera')->sole();
        $this->assertSame([$new->id, '900.00', 'of_remaining'], [$item->fresh()->bucket_id, $new->cap_amount->toDecimalString(), $new->fill_rule->value]);
        $this->assertSame(2, Bucket::count(), 'the old bucket stays');
    }

    public function test_a_new_bucket_needs_a_fill_rule_and_value_and_excludes_choosing_one(): void
    {
        $existing = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);

        $this->post('/planning/wishlist', $this->payload(['new_bucket' => true]))
            ->assertSessionHasErrors(['new_bucket_fill_rule', 'new_bucket_fill_value']);
        $this->post('/planning/wishlist', $this->payload(['new_bucket' => true, 'new_bucket_fill_rule' => 'percent', 'new_bucket_fill_value' => '101']))
            ->assertSessionHasErrors('new_bucket_fill_value');
        $this->post('/planning/wishlist', $this->payload(['new_bucket' => true, 'new_bucket_fill_rule' => 'fixed', 'new_bucket_fill_value' => '100', 'bucket_id' => $existing->id]))
            ->assertSessionHasErrors('bucket_id');

        $this->assertSame(0, WishlistItem::count());
        $this->assertSame(1, Bucket::count());
    }

    public function test_the_wish_list_tells_the_form_each_buckets_limit(): void
    {
        Bucket::factory()->savings()->for($this->user)->create(['name' => 'Capped', 'currency' => 'COP', 'cap_amount' => 3000000]);
        Bucket::factory()->savings()->for($this->user)->create(['name' => 'Open', 'currency' => 'COP', 'cap_amount' => null]);

        $this->get('/planning/wishlist')->assertInertia(fn (Assert $page) => $page
            ->where('buckets.0.name', 'Capped')
            ->where('buckets.0.cap', 3000000)
            ->where('buckets.1.cap', null)
        );
    }

    public function test_a_project_can_be_made_from_an_items_form(): void
    {
        $response = $this->postJson('/planning/projects/quick', ['name' => 'Photography', 'currency' => 'COP']);

        $response->assertCreated()->assertJsonPath('name', 'Photography')->assertJsonPath('currency', 'COP')->assertJsonPath('kind', 'project');
        $project = Project::sole();
        $this->assertSame([$response->json('id'), 'COP', null, null], [$project->id, $project->currency, $project->budget_amount, $project->bucket_id]);
    }

    public function test_a_quick_project_needs_a_name_and_a_currency(): void
    {
        $this->postJson('/planning/projects/quick', ['name' => '', 'currency' => 'CO'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'currency']);
        $this->assertSame(0, Project::count());
    }
}
