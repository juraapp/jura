<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\RecurringTransaction;
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

    public function test_user_can_bulk_delete_categories_and_those_with_transactions_are_kept(): void
    {
        $user = User::factory()->create();
        $free = Category::factory()->expense()->create(['user_id' => $user->id]);
        $used = Category::factory()->expense()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($used)->create();

        $response = $this->actingAs($user)->post('/settings/categories/bulk-delete', ['ids' => [$free->id, $used->id]]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('categories', ['id' => $free->id]);
        $this->assertDatabaseHas('categories', ['id' => $used->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_never_touches_system_or_other_users_categories(): void
    {
        $user = User::factory()->create();
        $system = Category::factory()->expense()->system()->create();
        $foreign = Category::factory()->expense()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->post('/settings/categories/bulk-delete', ['ids' => [$foreign->id]])->assertRedirect();
        $this->actingAs($user)->post('/settings/categories/bulk-delete', ['ids' => [$system->id]])->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $system->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('categories', ['id' => $foreign->id, 'deleted_at' => null]);
    }

    public function test_a_category_used_by_a_recurring_template_bucket_or_open_plan_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $forRecurring = Category::factory()->expense()->create(['user_id' => $user->id]);
        $forBucket = Category::factory()->expense()->create(['user_id' => $user->id]);
        $forPlan = Category::factory()->expense()->create(['user_id' => $user->id]);
        $free = Category::factory()->expense()->create(['user_id' => $user->id]);
        RecurringTransaction::factory()->for($user)->for($account)->for($forRecurring)->create();
        Bucket::factory()->for($user)->create(['category_id' => $forBucket->id]);
        PlannedEvent::factory()->for($user)->create(['type' => 'expense', 'category_id' => $forPlan->id]);

        $this->actingAs($user)->delete("/settings/categories/{$forRecurring->id}")->assertSessionHas('error');
        $this->post('/settings/categories/bulk-delete', ['ids' => [$forBucket->id, $forPlan->id, $free->id]])->assertRedirect();

        $this->assertNotSoftDeleted('categories', ['id' => $forRecurring->id]);
        $this->assertNotSoftDeleted('categories', ['id' => $forBucket->id]);
        $this->assertNotSoftDeleted('categories', ['id' => $forPlan->id]);
        $this->assertSoftDeleted('categories', ['id' => $free->id]);
    }

    public function test_a_trashed_category_still_used_by_a_recurring_template_cannot_be_purged(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        RecurringTransaction::factory()->for($user)->for($account)->for($category)->create();
        $category->delete();

        $this->actingAs($user)->delete("/settings/trash/categories/{$category->id}")->assertSessionHas('error');

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
