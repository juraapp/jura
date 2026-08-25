<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_only_shows_budgets_for_the_current_period(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['period_start' => Carbon::now()->startOfMonth()]);
        Budget::factory()->for($user)->create(['period_start' => Carbon::now()->subMonths(2)->startOfMonth()]);

        $response = $this->actingAs($user)->get('/planning/budgets');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Budgets/Index')
            ->has('budgets', 1)
        );
    }

    public function test_user_can_create_a_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/planning/budgets', [
            'category_id' => $category->id,
            'currency' => 'COP',
            'amount' => 500000,
            'period_type' => 'monthly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => '500000.00',
        ]);
    }

    public function test_creating_a_budget_for_an_existing_period_updates_the_amount_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $existing = Budget::factory()->for($user)->for($category)->create([
            'currency' => 'COP',
            'amount' => 100000,
            'period_start' => Carbon::now()->startOfMonth(),
        ]);

        $response = $this->actingAs($user)->post('/planning/budgets', [
            'category_id' => $category->id,
            'currency' => 'COP',
            'amount' => 750000,
            'period_type' => 'monthly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('budgets', 1);
        $this->assertSame('750000.00', $existing->fresh()->amount->toDecimalString());
    }

    public function test_creating_a_budget_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/planning/budgets', [
            'category_id' => 999999,
            'currency' => 'CO',
            'amount' => -5,
            'period_type' => 'weekly',
        ]);

        $response->assertSessionHasErrors(['category_id', 'currency', 'amount', 'period_type']);
    }

    public function test_user_can_update_their_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch("/planning/budgets/{$budget->id}", [
            'category_id' => $budget->category_id,
            'currency' => $budget->currency,
            'amount' => 999999,
            'period_type' => $budget->period_type->value,
        ]);

        $response->assertRedirect();
        $this->assertSame('999999.00', $budget->fresh()->amount->toDecimalString());
    }

    public function test_user_cannot_update_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/planning/budgets/{$budget->id}", [
            'category_id' => $budget->category_id,
            'currency' => $budget->currency,
            'amount' => 1,
            'period_type' => $budget->period_type->value,
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_delete_their_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/planning/budgets/{$budget->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_user_cannot_delete_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->delete("/planning/budgets/{$budget->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }
}
