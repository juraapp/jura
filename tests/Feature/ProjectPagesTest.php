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

class ProjectPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);
        $this->actingAs($this->user);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        auth()->logout();

        $this->get('/planning/projects')->assertRedirect('/login');
    }

    public function test_the_index_lists_my_projects_with_their_numbers_and_the_form_options(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['name' => 'Garage fund', 'currency' => 'COP']);
        $project = Project::factory()->for($this->user)->create(['name' => 'Garage', 'currency' => 'COP', 'budget_amount' => 2000000, 'target_date' => '2027-06-30', 'bucket_id' => $bucket->id]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'currency' => 'COP', 'price' => 500000]);
        Project::factory()->for(User::factory()->create())->create(['name' => 'Not mine']);

        $this->get('/planning/projects')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects', 1)
            ->where('projects.0.name', 'Garage')
            ->where('projects.0.currency', 'COP')
            ->where('projects.0.budget', 2000000)
            ->where('projects.0.targetDate', '2027-06-30')
            ->where('projects.0.bucket.name', 'Garage fund')
            ->where('projects.0.totals.0.wanted', 500000)
            ->has('projectKinds', 4)
            ->where('currencies', ['COP'])
            ->where('buckets.0.name', 'Garage fund')
            ->where('months', 24)
        );
    }

    public function test_items_in_no_project_come_last_as_their_own_entry(): void
    {
        Project::factory()->for($this->user)->create(['name' => 'Garage', 'currency' => 'COP']);
        WishlistItem::factory()->for($this->user)->create(['project_id' => null, 'currency' => 'COP', 'price' => 70000]);

        $this->get('/planning/projects')->assertInertia(fn (Assert $page) => $page
            ->has('projects', 2)
            ->where('projects.0.name', 'Garage')
            ->where('projects.1.id', null)
            ->where('projects.1.totals.0.wanted', 70000)
        );
    }

    public function test_a_project_page_shows_its_items_and_bucket(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['name' => 'Garage fund', 'currency' => 'COP']);
        $project = Project::factory()->for($this->user)->create(['name' => 'Garage', 'currency' => 'COP', 'bucket_id' => $bucket->id]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'name' => 'Lift', 'currency' => 'COP', 'price' => 500000]);

        $this->get("/planning/projects/{$project->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('project.name', 'Garage')
            ->where('project.bucket.name', 'Garage fund')
            ->where('project.bucket.envelope', 0)
            ->has('project.items', 1)
            ->where('project.items.0.name', 'Lift')
            ->where('project.items.0.bucket.fromProject', true)
            ->where('project.items.0.purchaseDate', $this->user->today()->toDateString()) // 1,000,000 in the account covers the 500,000 today
        );
    }

    public function test_someone_elses_project_is_not_found(): void
    {
        $theirs = Project::factory()->for(User::factory()->create())->create();

        $this->get("/planning/projects/{$theirs->id}")->assertNotFound();
        $this->delete("/planning/projects/{$theirs->id}")->assertNotFound();
        $this->assertSame(1, Project::withoutGlobalScopes()->count());
    }

    public function test_deleting_from_the_projects_own_page_goes_to_the_list_and_from_elsewhere_goes_back(): void
    {
        $first = Project::factory()->for($this->user)->create();
        $second = Project::factory()->for($this->user)->create();

        $this->from("/planning/projects/{$first->id}")->delete("/planning/projects/{$first->id}")
            ->assertRedirect('/planning/projects');
        $this->from('/planning/wishlist')->delete("/planning/projects/{$second->id}")
            ->assertRedirect('/planning/wishlist');
        $this->assertSame(0, Project::count());
    }

    public function test_the_wish_list_carries_the_projects_details_and_the_project_to_start_an_item_in(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['name' => 'Garage fund', 'currency' => 'COP']);
        $project = Project::factory()->for($this->user)->create(['name' => 'Garage', 'currency' => 'COP', 'budget_amount' => 900, 'bucket_id' => $bucket->id]);
        $other = Project::factory()->for(User::factory()->create())->create();

        $this->get("/planning/wishlist?project={$project->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Wishlist/Index')
            ->where('newItemProject', $project->id)
            ->where('projects.0.currency', 'COP')
            ->where('projects.0.budget', 900)
            ->where('projects.0.bucket.name', 'Garage fund')
        );
        $this->get("/planning/wishlist?project={$other->id}")->assertInertia(fn (Assert $page) => $page->where('newItemProject', null));
        $this->get('/planning/wishlist')->assertInertia(fn (Assert $page) => $page->where('newItemProject', null));
    }

    public function test_a_wish_list_item_shows_the_project_bucket_it_is_paid_from(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['name' => 'Garage fund', 'currency' => 'COP']);
        $project = Project::factory()->for($this->user)->create(['currency' => 'COP', 'bucket_id' => $bucket->id]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $project->id, 'currency' => 'COP', 'price' => 100]);

        $this->get('/planning/wishlist')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.bucket', null)
            ->where('items.0.projectBucket.name', 'Garage fund')
        );
    }
}
