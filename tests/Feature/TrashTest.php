<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Models\SavingsGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_trash_lists_only_the_users_deleted_records(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->for($user)->for(Account::factory()->for($user))->create()->delete();
        Transaction::factory()->for($user)->for(Account::factory()->for($user))->create();
        Account::factory()->for($user)->create()->delete();
        Category::factory()->create(['user_id' => $user->id])->delete();
        Transaction::factory()->create()->delete(); // someone else's

        $this->actingAs($user)->get('/settings/trash')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Trash/Index')
            ->has('transactions', 1)
            ->has('accounts', 1)
            ->has('categories', 1)
        );
    }

    public function test_a_deleted_transaction_can_be_restored(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->for(Account::factory()->for($user))->create();
        $transaction->delete();

        $this->actingAs($user)->post("/settings/trash/transactions/{$transaction->id}/restore")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNull($transaction->fresh()->deleted_at);
    }

    public function test_restoring_or_purging_a_transfer_leg_acts_on_both_legs(): void
    {
        $user = User::factory()->create();
        $group = (string) Str::uuid();
        $out = Transaction::factory()->for($user)->for(Account::factory()->for($user))->transferOut()->create(['transfer_group_id' => $group]);
        $in = Transaction::factory()->for($user)->for(Account::factory()->for($user))->transferIn()->create(['transfer_group_id' => $group]);
        $out->delete();
        $in->delete();

        $this->actingAs($user)->post("/settings/trash/transactions/{$out->id}/restore")->assertRedirect();
        $this->assertNull($out->fresh()->deleted_at);
        $this->assertNull($in->fresh()->deleted_at);

        $out->delete();
        $in->delete();
        $this->actingAs($user)->delete("/settings/trash/transactions/{$in->id}")->assertRedirect();
        $this->assertDatabaseMissing('transactions', ['id' => $out->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $in->id]);
    }

    public function test_a_deleted_transaction_can_be_deleted_permanently(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->for(Account::factory()->for($user))->create();
        $transaction->delete();

        $this->actingAs($user)->delete("/settings/trash/transactions/{$transaction->id}")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_active_transactions_and_other_users_records_cannot_be_purged(): void
    {
        $user = User::factory()->create();
        $active = Transaction::factory()->for($user)->for(Account::factory()->for($user))->create();
        $foreign = Transaction::factory()->create();
        $foreign->delete();

        $this->actingAs($user)->delete("/settings/trash/transactions/{$active->id}")->assertNotFound();
        $this->actingAs($user)->delete("/settings/trash/transactions/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->post("/settings/trash/transactions/{$foreign->id}/restore")->assertNotFound();

        $this->assertDatabaseHas('transactions', ['id' => $active->id]);
        $this->assertDatabaseHas('transactions', ['id' => $foreign->id]);
    }

    public function test_accounts_and_categories_can_be_restored_and_purged(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $account->delete();
        $category->delete();

        $this->actingAs($user)->post("/settings/trash/accounts/{$account->id}/restore")->assertRedirect();
        $this->assertNull($account->fresh()->deleted_at);

        $account->delete();
        $this->actingAs($user)->delete("/settings/trash/accounts/{$account->id}")->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);

        $this->actingAs($user)->post("/settings/trash/categories/{$category->id}/restore")->assertRedirect();
        $this->assertNull($category->fresh()->deleted_at);
        $category->delete();
        $this->actingAs($user)->delete("/settings/trash/categories/{$category->id}")->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_an_account_or_category_still_used_by_deleted_transactions_is_not_purged(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($account)->create(['category_id' => $category->id])->delete();
        $account->delete();
        $category->delete();

        $this->actingAs($user)->delete("/settings/trash/accounts/{$account->id}")->assertRedirect()->assertSessionHas('error');
        $this->actingAs($user)->delete("/settings/trash/categories/{$category->id}")->assertRedirect()->assertSessionHas('error');

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_a_deleted_savings_goal_can_be_restored_or_purged_from_the_goals_page(): void
    {
        $user = User::factory()->create();
        $restored = SavingsGoal::factory()->for($user)->create();
        $purged = SavingsGoal::factory()->for($user)->create();
        $restored->delete();
        $purged->delete();

        $this->actingAs($user)->get('/planning/goals')->assertInertia(fn (Assert $page) => $page->has('trashedGoals', 2));

        $this->actingAs($user)->post("/planning/goals/{$restored->id}/restore")->assertRedirect()->assertSessionHas('success');
        $this->actingAs($user)->delete("/planning/goals/{$purged->id}/force")->assertRedirect()->assertSessionHas('success');

        $this->assertNull($restored->fresh()->deleted_at);
        $this->assertDatabaseMissing('savings_goals', ['id' => $purged->id]);
    }
}
