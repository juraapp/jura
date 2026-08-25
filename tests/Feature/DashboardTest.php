<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_empty_state_when_the_user_has_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hasAnyAccount', false)
            ->where('hasAnyTransaction', false)
            ->where('currencies', [])
        );
    }

    public function test_dashboard_shows_summary_when_data_exists(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency' => 'COP']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($account)->for($category)->create([
            'date' => now(),
            'amount' => 50000,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hasAnyAccount', true)
            ->where('hasAnyTransaction', true)
            ->has('currencies', 1)
            ->has('currencies.0.summary')
        );
    }

    public function test_dashboard_defaults_evolution_months_to_six(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page->where('evolutionMonths', 6));
    }

    public function test_dashboard_accepts_twelve_month_evolution(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard?evolutionMonths=12');

        $response->assertInertia(fn (Assert $page) => $page->where('evolutionMonths', 12));
    }

    public function test_dashboard_rejects_unsupported_evolution_months(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard?evolutionMonths=3');

        $response->assertInertia(fn (Assert $page) => $page->where('evolutionMonths', 6));
    }

    public function test_net_worth_and_notifications_are_shared_as_deferred_props(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);

        // The asset version isn't knowable ahead of a real request (it's not
        // stable when resolved outside of route dispatch), so bootstrap it
        // off the 409 that an intentionally-stale version triggers.
        $probe = $this->actingAs($user)->getJson('/dashboard', ['X-Inertia' => 'true']);
        $version = $probe->headers->get('X-Inertia-Version');

        $response = $this->actingAs($user)->getJson('/dashboard', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $response->assertOk();
        $deferred = $response->json('deferredProps.topbar');
        $this->assertContains('netWorth', $deferred);
        $this->assertContains('notifications', $deferred);

        $partial = $this->actingAs($user)->getJson('/dashboard', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Data' => 'netWorth,notifications',
            'X-Inertia-Partial-Component' => 'Dashboard',
        ]);

        $partial->assertOk();
        $partial->assertJsonStructure(['props' => ['netWorth', 'notifications' => ['items', 'unreadCount']]]);
        $this->assertEquals(1000000.0, $partial->json('props.netWorth.COP'));
    }

    public function test_guests_cannot_access_the_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }
}
