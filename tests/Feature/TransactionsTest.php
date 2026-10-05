<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_is_displayed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->for($user)->for($account)->count(3)->create();
        Transaction::factory()->create(); // another user's

        $response = $this->actingAs($user)->get('/finances/transactions');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Index')
            ->where('typeFilter', 'all')
            ->has('transactions.data', 3)
        );
    }

    public function test_incomes_route_only_shows_income_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->for($user)->for($account)->income()->create();
        Transaction::factory()->for($user)->for($account)->create(); // expense

        $response = $this->actingAs($user)->get('/finances/incomes');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Index')
            ->where('typeFilter', 'income')
            ->has('transactions.data', 1)
        );
    }

    public function test_transfers_route_only_shows_transfer_legs(): void
    {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->for($user)->create();
        $toAccount = Account::factory()->for($user)->create();
        $groupId = (string) Str::uuid();
        Transaction::factory()->for($user)->for($fromAccount)->transferOut()->create(['transfer_group_id' => $groupId]);
        Transaction::factory()->for($user)->for($toAccount)->transferIn()->create(['transfer_group_id' => $groupId]);
        Transaction::factory()->for($user)->for($fromAccount)->create(); // regular expense

        $response = $this->actingAs($user)->get('/finances/transfers');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Index')
            ->where('typeFilter', 'transfer')
            ->has('transactions.data', 1)
        );
    }

    public function test_index_can_filter_by_search_term(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->for($user)->for($account)->create(['description' => 'Coffee shop']);
        Transaction::factory()->for($user)->for($account)->create(['description' => 'Bookstore']);

        $response = $this->actingAs($user)->get('/finances/transactions?q=coffee');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
        );
    }

    public function test_user_can_create_an_expense_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/finances/transactions', [
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 25000,
            'date' => now()->toDateString(),
            'description' => 'Lunch',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'expense',
            'description' => 'Lunch',
        ]);
    }

    public function test_user_can_create_an_income_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->income()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/finances/transactions', [
            'type' => 'income',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 3500000,
            'date' => now()->toDateString(),
            'description' => 'Salary',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'income',
            'description' => 'Salary',
        ]);
    }

    public function test_user_can_create_a_transfer(): void
    {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->for($user)->create();
        $toAccount = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/finances/transactions', [
            'type' => 'transfer',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 100000,
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', ['account_id' => $fromAccount->id, 'type' => 'transfer_out']);
        $this->assertDatabaseHas('transactions', ['account_id' => $toAccount->id, 'type' => 'transfer_in']);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_transfer_requires_distinct_accounts(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/finances/transactions', [
            'type' => 'transfer',
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 100000,
            'date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['from_account_id']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_user_cannot_create_a_transaction_against_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUsersAccount = Account::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/finances/transactions', [
            'type' => 'expense',
            'account_id' => $otherUsersAccount->id,
            'category_id' => $category->id,
            'amount' => 25000,
            'date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['account_id']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_user_can_update_a_simple_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->for($user)->for($account)->for($category)->create(['description' => 'Old']);

        $response = $this->actingAs($user)->patch("/finances/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => (string) $transaction->amount,
            'date' => $transaction->date->toDateString(),
            'description' => 'New description',
        ]);

        $response->assertRedirect();
        $this->assertSame('New description', $transaction->fresh()->description);
    }

    public function test_user_can_update_a_transfer(): void
    {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->for($user)->create();
        $toAccount = Account::factory()->for($user)->create();
        $groupId = (string) Str::uuid();
        $out = Transaction::factory()->for($user)->for($fromAccount)->transferOut()->create(['transfer_group_id' => $groupId, 'amount' => 50000]);
        $in = Transaction::factory()->for($user)->for($toAccount)->transferIn()->create(['transfer_group_id' => $groupId, 'amount' => 50000]);

        $response = $this->actingAs($user)->patch("/finances/transactions/{$out->id}", [
            'amount' => 75000,
            'date' => now()->toDateString(),
            'description' => 'Updated transfer',
        ]);

        $response->assertRedirect();
        $this->assertSame('75000.00', $out->fresh()->amount->toDecimalString());
        $this->assertSame('75000.00', $in->fresh()->amount->toDecimalString());
        $this->assertSame('Updated transfer', $in->fresh()->description);
    }

    public function test_user_cannot_update_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $category = Category::factory()->expense()->create(['user_id' => $owner->id]);
        $transaction = Transaction::factory()->for($owner)->for($account)->for($category)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/finances/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 1,
            'date' => now()->toDateString(),
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_delete_a_simple_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create();

        $response = $this->actingAs($user)->delete("/finances/transactions/{$transaction->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_deleting_a_transfer_leg_deletes_both_legs(): void
    {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->for($user)->create();
        $toAccount = Account::factory()->for($user)->create();
        $groupId = (string) Str::uuid();
        $out = Transaction::factory()->for($user)->for($fromAccount)->transferOut()->create(['transfer_group_id' => $groupId]);
        $in = Transaction::factory()->for($user)->for($toAccount)->transferIn()->create(['transfer_group_id' => $groupId]);

        $response = $this->actingAs($user)->delete("/finances/transactions/{$out->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('transactions', ['id' => $out->id]);
        $this->assertSoftDeleted('transactions', ['id' => $in->id]);
    }

    public function test_user_cannot_delete_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $transaction = Transaction::factory()->for($owner)->for($account)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->delete("/finances/transactions/{$transaction->id}");

        $response->assertNotFound();
    }

    public function test_form_options_endpoint_returns_accounts_and_categories(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();
        Category::factory()->expense()->create(['user_id' => $user->id]);
        Category::factory()->income()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/finances/transactions/form-options');

        $response->assertOk();
        $response->assertJsonStructure(['accounts', 'expenseCategories', 'incomeCategories']);
    }

    public function test_edit_data_endpoint_returns_simple_transaction_data(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->for($user)->for($account)->for($category)->create();

        $response = $this->actingAs($user)->getJson("/finances/transactions/{$transaction->id}/edit-data");

        $response->assertOk();
        $response->assertJson([
            'id' => $transaction->id,
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_edit_data_endpoint_returns_transfer_data_with_both_accounts(): void
    {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->for($user)->create();
        $toAccount = Account::factory()->for($user)->create();
        $groupId = (string) Str::uuid();
        $out = Transaction::factory()->for($user)->for($fromAccount)->transferOut()->create(['transfer_group_id' => $groupId]);
        Transaction::factory()->for($user)->for($toAccount)->transferIn()->create(['transfer_group_id' => $groupId]);

        $response = $this->actingAs($user)->getJson("/finances/transactions/{$out->id}/edit-data");

        $response->assertOk();
        $response->assertJson([
            'type' => 'transfer',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
        ]);
    }

    public function test_user_cannot_view_edit_data_for_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $transaction = Transaction::factory()->for($owner)->for($account)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->getJson("/finances/transactions/{$transaction->id}/edit-data");

        $response->assertNotFound();
    }
}
