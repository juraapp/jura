<?php

namespace Tests\Feature;

use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SavingsGoalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_shows_active_and_completed_goals_but_not_archived(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->for($user)->create();
        SavingsGoal::factory()->for($user)->completed()->create();
        SavingsGoal::factory()->for($user)->archived()->create();

        $response = $this->actingAs($user)->get('/planning/goals');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SavingsGoals/Index')
            ->has('goals', 2)
        );
    }

    public function test_user_can_create_a_savings_goal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/planning/goals', [
            'name' => 'Buy a laptop',
            'target_amount' => 5000000,
            'currency' => 'COP',
            'target_date' => now()->addYear()->toDateString(),
            'icon' => 'flag',
            'color' => '#2563eb',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('savings_goals', [
            'user_id' => $user->id,
            'name' => 'Buy a laptop',
        ]);
    }

    public function test_creating_a_savings_goal_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/planning/goals', [
            'name' => '',
            'target_amount' => -1,
            'currency' => 'CO',
            'target_date' => 'yesterday',
            'icon' => '',
            'color' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'target_amount', 'currency', 'icon', 'color']);
    }

    public function test_user_can_update_their_savings_goal(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create(['name' => 'Old']);

        $response = $this->actingAs($user)->patch("/planning/goals/{$goal->id}", [
            'name' => 'New name',
            'target_amount' => (string) $goal->target_amount,
            'currency' => $goal->currency,
            'target_date' => $goal->target_date->toDateString(),
            'icon' => $goal->icon,
            'color' => $goal->color,
        ]);

        $response->assertRedirect();
        $this->assertSame('New name', $goal->fresh()->name);
    }

    public function test_user_cannot_update_another_users_savings_goal(): void
    {
        $owner = User::factory()->create();
        $goal = SavingsGoal::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/planning/goals/{$goal->id}", [
            'name' => 'Hijacked',
            'target_amount' => (string) $goal->target_amount,
            'currency' => $goal->currency,
            'icon' => $goal->icon,
            'color' => $goal->color,
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_delete_their_savings_goal(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/planning/goals/{$goal->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('savings_goals', ['id' => $goal->id]);
    }

    public function test_user_cannot_delete_another_users_savings_goal(): void
    {
        $owner = User::factory()->create();
        $goal = SavingsGoal::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->delete("/planning/goals/{$goal->id}");

        $response->assertNotFound();
    }

    public function test_recording_a_contribution_below_target_leaves_the_goal_active(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 1000000]);

        $response = $this->actingAs($user)->post("/planning/goals/{$goal->id}/contributions", [
            'amount' => 200000,
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('savings_goal_contributions', [
            'savings_goal_id' => $goal->id,
            'amount' => '200000.00',
        ]);
        $this->assertSame('active', $goal->fresh()->status->value);
    }

    public function test_recording_a_contribution_that_reaches_the_target_completes_the_goal(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 1000000]);

        $response = $this->actingAs($user)->post("/planning/goals/{$goal->id}/contributions", [
            'amount' => 1000000,
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertSame('completed', $goal->fresh()->status->value);
    }

    public function test_recording_a_contribution_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();

        $response = $this->actingAs($user)->post("/planning/goals/{$goal->id}/contributions", [
            'amount' => 0,
            'date' => '',
        ]);

        $response->assertSessionHasErrors(['amount', 'date']);
    }

    public function test_user_cannot_record_a_contribution_on_another_users_goal(): void
    {
        $owner = User::factory()->create();
        $goal = SavingsGoal::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->post("/planning/goals/{$goal->id}/contributions", [
            'amount' => 1000,
            'date' => now()->toDateString(),
        ]);

        $response->assertNotFound();
    }
}
