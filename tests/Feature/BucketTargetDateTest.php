<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BucketTargetDateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);
        $this->actingAs($this->user);
    }

    private function savings(array $overrides = []): array
    {
        return array_merge(['kind' => 'savings', 'name' => 'Trip', 'currency' => 'COP', 'cap_amount' => 1000000, 'target_date' => '2027-01-12'], $overrides);
    }

    public function test_a_savings_bucket_keeps_its_target_date(): void
    {
        $this->post('/planning/buckets', $this->savings())->assertSessionHasNoErrors();

        $this->assertSame('2027-01-12', Bucket::sole()->target_date->toDateString());
    }

    public function test_a_target_date_needs_a_target_amount(): void
    {
        $this->post('/planning/buckets', $this->savings(['cap_amount' => null]))->assertSessionHasErrors('target_date');
        $this->assertSame(0, Bucket::count());
    }

    public function test_a_spending_bucket_cannot_have_a_target_date(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $this->post('/planning/buckets', ['kind' => 'spending', 'category_id' => $category->id, 'currency' => 'COP', 'cap_amount' => 500, 'cycle' => 'monthly', 'target_date' => '2027-01-12'])
            ->assertSessionHasErrors('target_date');
    }

    public function test_the_target_date_can_be_removed_again(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'cap_amount' => 1000000, 'target_date' => '2027-01-12']);

        $this->patch("/planning/buckets/{$bucket->id}", $this->savings(['target_date' => null]))->assertSessionHasNoErrors();

        $this->assertNull($bucket->fresh()->target_date);
    }

    public function test_the_bucket_card_says_how_the_target_is_going(): void
    {
        // Made on Sep 1, target Jan 12 (133 days, 41 gone: 30.8%). 100,000 of 1,000,000 saved is 10%: behind, and the
        // 900,000 missing over three months is 300,000 a month.
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'cap_amount' => 1000000, 'target_date' => '2027-01-12', 'created_at' => '2026-09-01']);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => 100000, 'currency' => 'COP']);
        Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'No date']);

        $this->get('/planning/buckets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Buckets/Index')
            ->where('buckets.0.targetDate', '2027-01-12')
            ->where('buckets.0.target.behind', true)
            ->where('buckets.0.target.percent', 10)
            ->where('buckets.0.target.neededPerMonth', 300000)
            ->where('buckets.0.target.basis', 'pace')
            ->where('buckets.1.targetDate', null)
            ->where('buckets.1.target', null)
        );
    }
}
