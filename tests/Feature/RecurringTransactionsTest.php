<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecurringTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_is_displayed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        RecurringTransaction::factory()->for($user)->for($account)->for($category)->count(2)->create();
        RecurringTransaction::factory()->create(); // another user's

        $response = $this->actingAs($user)->get('/planning/recurring');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('RecurringTransactions/Index')
            ->has('recurrences', 2)
        );
    }

    public function test_user_can_create_a_recurring_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 45000,
            'description' => 'Netflix',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDays(5)->toDateString(),
            'end_date' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $user->id,
            'description' => 'Netflix',
            'is_active' => true,
        ]);
    }

    public function test_creating_a_recurring_transaction_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'transfer',
            'account_id' => 999999,
            'category_id' => 999999,
            'amount' => -5,
            'frequency' => 'daily',
            'next_due_date' => '',
        ]);

        $response->assertSessionHasErrors(['type', 'account_id', 'category_id', 'amount', 'frequency', 'next_due_date']);
    }

    public function test_user_cannot_create_a_recurring_transaction_against_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUsersAccount = Account::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'expense',
            'account_id' => $otherUsersAccount->id,
            'category_id' => $category->id,
            'amount' => 45000,
            'frequency' => 'monthly',
            'next_due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['account_id']);
        $this->assertDatabaseCount('recurring_transactions', 0);
    }

    public function test_user_cannot_create_a_recurring_transaction_against_another_users_private_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherUser = User::factory()->create();
        $otherUsersCategory = Category::factory()->expense()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $otherUsersCategory->id,
            'amount' => 45000,
            'frequency' => 'monthly',
            'next_due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['category_id']);
        $this->assertDatabaseCount('recurring_transactions', 0);
    }

    public function test_user_can_update_their_recurring_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $recurring = RecurringTransaction::factory()->for($user)->for($account)->for($category)->create(['description' => 'Old']);

        $response = $this->actingAs($user)->patch("/planning/recurring/{$recurring->id}", [
            'type' => $recurring->type->value,
            'account_id' => $recurring->account_id,
            'category_id' => $recurring->category_id,
            'amount' => (string) $recurring->amount,
            'description' => 'New description',
            'frequency' => $recurring->frequency->value,
            'next_due_date' => $recurring->next_due_date->toDateString(),
            'end_date' => null,
        ]);

        $response->assertRedirect();
        $this->assertSame('New description', $recurring->fresh()->description);
    }

    public function test_user_cannot_update_another_users_recurring_transaction(): void
    {
        $owner = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/planning/recurring/{$recurring->id}", [
            'type' => $recurring->type->value,
            'account_id' => $recurring->account_id,
            'category_id' => $recurring->category_id,
            'amount' => (string) $recurring->amount,
            'frequency' => $recurring->frequency->value,
            'next_due_date' => $recurring->next_due_date->toDateString(),
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_toggle_active_state(): void
    {
        $user = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($user)->create(['is_active' => true]);

        $response = $this->actingAs($user)->patch("/planning/recurring/{$recurring->id}/toggle");

        $response->assertRedirect();
        $this->assertFalse($recurring->fresh()->is_active);
    }

    public function test_user_cannot_toggle_another_users_recurring_transaction(): void
    {
        $owner = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($owner)->create(['is_active' => true]);
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/planning/recurring/{$recurring->id}/toggle");

        $response->assertNotFound();
        $this->assertTrue($recurring->fresh()->is_active);
    }

    public function test_user_can_delete_their_recurring_transaction(): void
    {
        $user = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/planning/recurring/{$recurring->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('recurring_transactions', ['id' => $recurring->id]);
    }

    public function test_user_cannot_delete_another_users_recurring_transaction(): void
    {
        $owner = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->delete("/planning/recurring/{$recurring->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('recurring_transactions', ['id' => $recurring->id]);
    }
}
