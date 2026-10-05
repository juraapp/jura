<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_shows_system_and_owned_categories_grouped_by_type(): void
    {
        // The categories table is pre-seeded with real system categories via
        // the migration itself, so counts are relative to that baseline
        // rather than an assumed-empty table.
        $seededExpenseCount = Category::query()->withoutGlobalScopes()->where('type', 'expense')->count();
        $seededIncomeCount = Category::query()->withoutGlobalScopes()->where('type', 'income')->count();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Category::factory()->expense()->system()->create();
        Category::factory()->expense()->create(['user_id' => $user->id]);
        Category::factory()->income()->create(['user_id' => $user->id]);
        Category::factory()->expense()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/settings/categories');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Index')
            ->has('expenseCategories', $seededExpenseCount + 2)
            ->has('incomeCategories', $seededIncomeCount + 1)
        );
    }

    public function test_user_can_create_a_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/categories', [
            'name' => 'Groceries',
            'type' => 'expense',
            'icon' => 'tag',
            'color' => '#f59e0b',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Groceries',
            'type' => 'expense',
            'is_system' => false,
        ]);
    }

    public function test_creating_a_category_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/categories', [
            'name' => '',
            'type' => 'not-a-type',
            'icon' => '',
            'color' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'type', 'icon', 'color']);
    }

    public function test_user_can_update_their_own_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Old']);

        $response = $this->actingAs($user)->patch("/settings/categories/{$category->id}", [
            'name' => 'New name',
            'icon' => $category->icon,
            'color' => $category->color,
        ]);

        $response->assertRedirect();
        $this->assertSame('New name', $category->fresh()->name);
    }

    public function test_user_cannot_update_a_system_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->system()->create();

        $response = $this->actingAs($user)->patch("/settings/categories/{$category->id}", [
            'name' => 'Hijacked',
            'icon' => $category->icon,
            'color' => $category->color,
        ]);

        $response->assertForbidden();
        $this->assertNotSame('Hijacked', $category->fresh()->name);
    }

    public function test_user_cannot_update_another_users_category(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        // Categories use a custom visibility scope (owner OR system), so an
        // intruder's private-category lookup finds nothing at all: a 404.
        $response = $this->actingAs($intruder)->patch("/settings/categories/{$category->id}", [
            'name' => 'Hijacked',
            'icon' => $category->icon,
            'color' => $category->color,
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_delete_a_category_without_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/settings/categories/{$category->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_delete_a_category_with_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($category)->create();

        $response = $this->actingAs($user)->delete("/settings/categories/{$category->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_user_cannot_delete_a_system_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->system()->create();

        $response = $this->actingAs($user)->delete("/settings/categories/{$category->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }
}
