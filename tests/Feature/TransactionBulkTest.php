<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->create();
    }

    private function expense(array $attributes = []): Transaction
    {
        return Transaction::factory()->for($this->user)->for($this->account)->create($attributes);
    }

    public function test_many_transactions_are_deleted_at_once_and_an_undo_is_offered(): void
    {
        $rows = collect([$this->expense(), $this->expense(), $this->expense()]);

        $response = $this->actingAs($this->user)->post('/finances/transactions/bulk-delete', ['ids' => $rows->take(2)->pluck('id')->all()]);

        $response->assertRedirect()->assertSessionHas('success');
        $response->assertSessionHas('undo.ids', fn ($ids) => count($ids) === 2);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(2, Transaction::onlyTrashed()->count());
    }

    public function test_other_users_transactions_are_ignored(): void
    {
        $foreign = Transaction::factory()->create();
        $own = $this->expense();

        $this->actingAs($this->user)->post('/finances/transactions/bulk-delete', ['ids' => [$foreign->id, $own->id]])->assertRedirect();

        $this->assertNull(Transaction::withoutGlobalScopes()->find($foreign->id)->deleted_at);
        $this->assertSoftDeleted('transactions', ['id' => $own->id]);
    }

    public function test_deleting_one_transfer_leg_in_bulk_removes_both_legs_once(): void
    {
        $other = Account::factory()->for($this->user)->create();
        $group = (string) Str::uuid();
        $out = $this->expense(['type' => 'transfer_out', 'category_id' => null, 'transfer_group_id' => $group]);
        $in = Transaction::factory()->for($this->user)->for($other)->create(['type' => 'transfer_in', 'category_id' => null, 'transfer_group_id' => $group]);

        $this->actingAs($this->user)->post('/finances/transactions/bulk-delete', ['ids' => [$out->id, $in->id]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, Transaction::count());
        $this->assertSame(2, Transaction::onlyTrashed()->count());
    }

    public function test_undo_restores_the_deleted_transactions(): void
    {
        $rows = collect([$this->expense(), $this->expense()]);
        $ids = $rows->pluck('id')->all();
        $this->actingAs($this->user)->post('/finances/transactions/bulk-delete', ['ids' => $ids]);

        $this->actingAs($this->user)->post('/finances/transactions/bulk-restore', ['ids' => $ids])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, Transaction::count());
    }

    public function test_everything_matching_the_filters_can_be_deleted_not_just_the_page(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        foreach (range(1, 20) as $i) {
            $this->expense(['category_id' => $category->id]);
        }
        $this->expense();

        $this->actingAs($this->user)->post('/finances/transactions/bulk-delete', [
            'all_matching' => true,
            'filters' => ['category' => $category->id],
        ])->assertRedirect();

        $this->assertSame(1, Transaction::count());
    }

    public function test_the_ids_are_required_and_limited(): void
    {
        $this->actingAs($this->user)->post('/finances/transactions/bulk-delete', [])->assertSessionHasErrors('ids');
        $this->actingAs($this->user)->post('/finances/transactions/bulk-delete', ['ids' => range(1, 501)])->assertSessionHasErrors('ids');
    }

    public function test_category_and_account_can_be_changed_in_bulk_and_transfers_are_left_alone(): void
    {
        $newAccount = Account::factory()->for($this->user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $incomeCategory = Category::factory()->income()->create(['user_id' => $this->user->id]);
        $a = $this->expense();
        $b = $this->expense();
        $income = Transaction::factory()->for($this->user)->for($this->account)->income()->create();
        $group = (string) Str::uuid();
        $transfer = $this->expense(['type' => 'transfer_out', 'category_id' => null, 'transfer_group_id' => $group]);

        $this->actingAs($this->user)->post('/finances/transactions/bulk-update', [
            'ids' => [$a->id, $b->id, $income->id, $transfer->id],
            'category_id' => $category->id,
            'account_id' => $newAccount->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame($category->id, $a->fresh()->category_id);
        $this->assertSame($newAccount->id, $b->fresh()->account_id);
        $this->assertSame($newAccount->id, $income->fresh()->account_id, 'account changes for any simple transaction');
        $this->assertNotSame($category->id, $income->fresh()->category_id, 'an expense category never lands on an income');
        $this->assertSame($this->account->id, $transfer->fresh()->account_id, 'transfers are untouched');
        $this->assertNotNull($incomeCategory);
    }

    public function test_bulk_update_needs_something_to_change_and_rejects_foreign_targets(): void
    {
        $row = $this->expense();
        $foreignAccount = Account::factory()->create();

        $this->actingAs($this->user)->post('/finances/transactions/bulk-update', ['ids' => [$row->id]])->assertSessionHasErrors('category_id');
        $this->actingAs($this->user)->post('/finances/transactions/bulk-update', ['ids' => [$row->id], 'account_id' => $foreignAccount->id])->assertSessionHasErrors('account_id');
    }
}
