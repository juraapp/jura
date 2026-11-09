<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A project's currency, budget, target date and savings bucket, and how a wish-list purchase uses the bucket of
 * its item or of its project. Every expected number is worked out by hand in the comments.
 */
class ProjectFundingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $bank;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->bank = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
        $this->food = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    private function projectPayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Garage', 'kind' => 'project', 'currency' => 'COP'], $overrides);
    }

    private function savingsBucket(string $name = 'Bike', string $currency = 'COP'): Bucket
    {
        return Bucket::factory()->savings()->for($this->user)->create(['name' => $name, 'currency' => $currency]);
    }

    private function credit(Bucket $bucket, int $amount): void
    {
        app(BucketLedger::class)->credit($bucket, Money::of($amount), AllocationSource::Adjustment, null, null);
    }

    private function envelope(Bucket $bucket): string
    {
        return app(BucketLedger::class)->envelope($bucket->fresh())->toDecimalString();
    }

    private function readyToAssign(): string
    {
        return app(MoneyMap::class)->readyToAssign($this->user->fresh(), 'COP')->toDecimalString();
    }

    public function test_a_project_keeps_its_currency_budget_and_target_date(): void
    {
        $this->post('/planning/wishlist/projects', $this->projectPayload(['budget_amount' => '3000000.50', 'target_date' => '2027-06-30']))
            ->assertSessionHasNoErrors();

        $project = Project::sole();
        $this->assertSame(['COP', '3000000.50', '2027-06-30', null], [
            $project->currency, $project->budget_amount->toDecimalString(), $project->target_date->toDateString(), $project->bucket_id,
        ]);
    }

    public function test_a_budget_needs_a_currency(): void
    {
        $this->post('/planning/wishlist/projects', ['name' => 'Garage', 'kind' => 'project', 'budget_amount' => '100'])
            ->assertSessionHasErrors('currency');
    }

    public function test_a_project_can_use_one_of_my_savings_buckets_once_and_only_in_its_currency(): void
    {
        $bike = $this->savingsBucket();
        $dollars = $this->savingsBucket('Trip', 'USD');

        $this->post('/planning/wishlist/projects', $this->projectPayload(['bucket_mode' => 'existing', 'bucket_id' => $bike->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($bike->id, Project::sole()->bucket_id);

        $this->post('/planning/wishlist/projects', $this->projectPayload(['name' => 'Second', 'bucket_mode' => 'existing', 'bucket_id' => $bike->id]))
            ->assertSessionHasErrors('bucket_id');
        $this->post('/planning/wishlist/projects', $this->projectPayload(['name' => 'Third', 'bucket_mode' => 'existing', 'bucket_id' => $dollars->id]))
            ->assertSessionHasErrors('bucket_id');
        $this->assertSame(1, Project::count());
    }

    public function test_someone_elses_bucket_is_not_accepted(): void
    {
        $stranger = User::factory()->create();
        $theirs = Bucket::factory()->savings()->for($stranger)->create(['currency' => 'COP']);

        $this->post('/planning/wishlist/projects', $this->projectPayload(['bucket_mode' => 'existing', 'bucket_id' => $theirs->id]))
            ->assertSessionHasErrors('bucket_id');
    }

    public function test_a_new_bucket_targets_the_budget_and_fills_a_fixed_amount_every_month(): void
    {
        $this->post('/planning/wishlist/projects', $this->projectPayload([
            'budget_amount' => '3000000', 'bucket_mode' => 'new', 'new_bucket' => ['fill_rule' => 'fixed', 'fill_value' => '500000'],
        ]))->assertSessionHasNoErrors();

        $project = Project::sole();
        $bucket = Bucket::sole();
        $this->assertSame($bucket->id, $project->bucket_id);
        $this->assertSame(['Garage', 'savings', 'COP', '3000000.00', 'fixed', '500000.0000', 'monthly'], [
            $bucket->name, $bucket->kind->value, $bucket->currency, $bucket->cap_amount->toDecimalString(),
            $bucket->fill_rule->value, (string) $bucket->fill_value, $bucket->cycle->value,
        ]);
    }

    public function test_without_a_budget_the_new_bucket_targets_what_the_wanted_items_cost(): void
    {
        $project = Project::factory()->for($this->user)->create(['currency' => 'COP']);
        // 1,000,000 + 2,500,000 wanted; the 400,000 purchased item and the USD item do not count.
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'currency' => 'COP', 'price' => 1000000]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'currency' => 'COP', 'price' => 2500000]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'currency' => 'COP', 'price' => 400000, 'status' => 'purchased']);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'currency' => 'USD', 'price' => 99]);

        $this->patch("/planning/wishlist/projects/{$project->id}", $this->projectPayload([
            'bucket_mode' => 'new', 'new_bucket' => ['fill_rule' => 'percent', 'fill_value' => '25'],
        ]))->assertSessionHasNoErrors();

        $bucket = Bucket::sole();
        $this->assertSame(['3500000.00', 'percent', null], [$bucket->cap_amount->toDecimalString(), $bucket->fill_rule->value, $bucket->cycle?->value]);
        $this->assertSame($bucket->id, $project->fresh()->bucket_id);
    }

    public function test_a_new_bucket_needs_a_fill_rule_and_value(): void
    {
        $this->post('/planning/wishlist/projects', $this->projectPayload(['bucket_mode' => 'new']))
            ->assertSessionHasErrors(['new_bucket.fill_rule']);
        $this->post('/planning/wishlist/projects', $this->projectPayload(['bucket_mode' => 'new', 'new_bucket' => ['fill_rule' => 'fixed']]))
            ->assertSessionHasErrors(['new_bucket.fill_value']);
        $this->post('/planning/wishlist/projects', $this->projectPayload(['bucket_mode' => 'new', 'new_bucket' => ['fill_rule' => 'percent', 'fill_value' => '101']]))
            ->assertSessionHasErrors(['new_bucket.fill_value']);
    }

    public function test_deleting_a_project_keeps_its_bucket_unless_asked(): void
    {
        $keep = $this->savingsBucket('Keep');
        $drop = $this->savingsBucket('Drop');
        $first = Project::factory()->for($this->user)->create(['currency' => 'COP', 'bucket_id' => $keep->id]);
        $second = Project::factory()->for($this->user)->create(['currency' => 'COP', 'bucket_id' => $drop->id]);
        $this->credit($drop, 300);
        // 1,000 in the account, 300 earmarked in "Drop".
        $this->assertSame('700.00', $this->readyToAssign());

        $this->delete("/planning/wishlist/projects/{$first->id}")->assertSessionHasNoErrors();
        $this->delete("/planning/wishlist/projects/{$second->id}", ['delete_bucket' => true])->assertSessionHasNoErrors();

        $this->assertSame(0, Project::count());
        $this->assertSame(['Keep'], Bucket::pluck('name')->all());
        $this->assertSame('1000.00', $this->readyToAssign(), 'the deleted bucket handed its 300 back');
    }

    public function test_an_item_must_use_its_projects_currency(): void
    {
        $project = Project::factory()->for($this->user)->create(['currency' => 'COP']);

        $this->post('/planning/wishlist', ['name' => 'Camera', 'price' => '100', 'currency' => 'USD', 'project_id' => $project->id])
            ->assertSessionHasErrors('project_id');
        $this->post('/planning/wishlist', ['name' => 'Camera', 'price' => '100', 'currency' => 'COP', 'project_id' => $project->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, WishlistItem::count());
    }

    public function test_an_item_without_a_bucket_is_paid_from_its_projects_bucket(): void
    {
        $bucket = $this->savingsBucket('Garage fund');
        $project = Project::factory()->for($this->user)->create(['currency' => 'COP', 'bucket_id' => $bucket->id]);
        $this->credit($bucket, 800);
        $item = WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'currency' => 'COP', 'price' => 500]);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $this->bank->id, 'category_id' => $this->food->id])
            ->assertSessionHasNoErrors();

        // The bucket pays the 500 (800 -> 300). The account goes 1,000 -> 500 and 300 is still earmarked, so 200 is
        // ready to assign.
        $this->assertSame('300.00', $this->envelope($bucket));
        $this->assertSame('200.00', $this->readyToAssign());
        $this->assertSame($bucket->id, Transaction::sole()->bucket_id);
        $this->assertSame(1, Allocation::where('bucket_id', $bucket->id)->where('source', 'spend')->count());
    }

    public function test_the_items_own_bucket_wins_over_its_projects(): void
    {
        $projectBucket = $this->savingsBucket('Garage fund');
        $ownBucket = $this->savingsBucket('Camera fund');
        $project = Project::factory()->for($this->user)->create(['currency' => 'COP', 'bucket_id' => $projectBucket->id]);
        $this->credit($projectBucket, 800);
        $this->credit($ownBucket, 400);
        $item = WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'bucket_id' => $ownBucket->id, 'currency' => 'COP', 'price' => 300]);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $this->bank->id, 'category_id' => $this->food->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(['800.00', '100.00'], [$this->envelope($projectBucket), $this->envelope($ownBucket)]);
    }
}
