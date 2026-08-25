<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_is_displayed(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->count(2)->create();
        Account::factory()->create(); // another user's account, must not appear

        $response = $this->actingAs($user)->get('/accounts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Index')
            ->has('accounts', 2)
            ->has('accountTypes')
        );
    }

    public function test_user_can_create_an_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/accounts', [
            'name' => 'Main checking',
            'type' => 'bank',
            'institution' => 'Bancolombia',
            'initial_balance' => 1500000,
            'currency' => 'COP',
            'color' => '#2563eb',
            'masked_number' => '1234',
            'is_active' => true,
            'notes' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Main checking',
            'type' => 'bank',
        ]);
    }

    public function test_creating_an_account_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/accounts', [
            'name' => '',
            'type' => 'not-a-real-type',
            'initial_balance' => 'not-a-number',
            'currency' => 'CO',
            'color' => '#2563eb',
        ]);

        $response->assertSessionHasErrors(['name', 'type', 'initial_balance', 'currency']);
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_user_can_update_their_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Old name']);

        $response = $this->actingAs($user)->patch("/accounts/{$account->id}", [
            'name' => 'New name',
            'type' => $account->type->value,
            'institution' => $account->institution,
            'initial_balance' => (string) $account->initial_balance,
            'currency' => $account->currency,
            'color' => $account->color,
            'masked_number' => null,
            'is_active' => true,
            'notes' => null,
        ]);

        $response->assertRedirect();
        $this->assertSame('New name', $account->fresh()->name);
    }

    public function test_user_cannot_update_another_users_account(): void
    {
        $owner = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        // Accounts are scoped to the owning user via a global query scope, so
        // an intruder's route-model-bound lookup never finds the row at all —
        // a 404, not a 403, which avoids leaking whether the ID even exists.
        $response = $this->actingAs($intruder)->patch("/accounts/{$account->id}", [
            'name' => 'Hijacked',
            'type' => $account->type->value,
            'initial_balance' => (string) $account->initial_balance,
            'currency' => $account->currency,
            'color' => $account->color,
        ]);

        $response->assertNotFound();
        $this->assertSame($account->name, $account->fresh()->name);
    }

    public function test_deleting_an_account_without_transactions_deletes_it(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/accounts/{$account->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_deleting_an_account_with_transactions_archives_it_instead(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['is_active' => true]);
        Transaction::factory()->for($user)->for($account)->create();

        $response = $this->actingAs($user)->delete("/accounts/{$account->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'is_active' => false]);
    }

    public function test_user_cannot_delete_another_users_account(): void
    {
        $owner = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->delete("/accounts/{$account->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'deleted_at' => null]);
    }

    public function test_guests_cannot_access_accounts(): void
    {
        $response = $this->get('/accounts');

        $response->assertRedirect('/login');
    }
}
