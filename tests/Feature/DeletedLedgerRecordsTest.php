<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Services\TransactionService;
use App\Modules\Planning\Models\RecurringTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * Trashed and archived accounts and categories can't receive new money, and the rows that already
 * point at them never break a page.
 */
class DeletedLedgerRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->create();
        $this->category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function expense(array $overrides = []): array
    {
        return array_merge([
            'type' => 'expense', 'account_id' => $this->account->id, 'category_id' => $this->category->id,
            'amount' => 10, 'date' => '2026-10-12',
        ], $overrides);
    }

    public function test_a_transaction_cannot_be_saved_to_a_trashed_account(): void
    {
        $this->account->delete();

        $this->post('/finances/transactions', $this->expense())->assertSessionHasErrors('account_id');

        $this->assertSame(0, Transaction::withTrashed()->count());
    }

    public function test_a_transaction_cannot_be_filed_under_a_trashed_category(): void
    {
        $this->category->delete();

        $this->post('/finances/transactions', $this->expense())->assertSessionHasErrors('category_id');

        $this->assertSame(0, Transaction::withTrashed()->count());
    }

    public function test_an_archived_account_cannot_receive_new_transactions_or_transfers(): void
    {
        $archived = Account::factory()->for($this->user)->inactive()->create();

        $this->post('/finances/transactions', $this->expense(['account_id' => $archived->id]))->assertSessionHasErrors('account_id');
        $this->post('/finances/transactions', [
            'type' => 'transfer', 'from_account_id' => $this->account->id, 'to_account_id' => $archived->id, 'amount' => 10, 'date' => '2026-10-12',
        ])->assertSessionHasErrors('to_account_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_a_transaction_already_on_an_archived_account_can_still_be_edited(): void
    {
        $transaction = Transaction::factory()->for($this->user)->for($this->account)->create(['category_id' => $this->category->id]);
        $this->account->update(['is_active' => false]);

        $this->patch("/finances/transactions/{$transaction->id}", $this->expense(['amount' => 25]))->assertSessionHasNoErrors();

        $this->assertSame('25.00', $transaction->fresh()->amount->toDecimalString());
    }

    public function test_a_transaction_whose_category_was_deleted_can_still_be_edited_but_not_moved_to_another_trashed_one(): void
    {
        $transaction = Transaction::factory()->for($this->user)->for($this->account)->create(['category_id' => $this->category->id, 'type' => 'expense']);
        $otherTrashed = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        Category::whereKey([$this->category->id, $otherTrashed->id])->get()->each->delete();

        $this->patch("/finances/transactions/{$transaction->id}", $this->expense(['amount' => 25]))->assertSessionHasNoErrors();
        $this->assertSame('25.00', $transaction->fresh()->amount->toDecimalString());

        $this->patch("/finances/transactions/{$transaction->id}", $this->expense(['category_id' => $otherTrashed->id]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_a_recurring_template_cannot_be_moved_to_a_trashed_account(): void
    {
        $trashed = Account::factory()->for($this->user)->create();
        $trashed->delete();
        $recurring = RecurringTransaction::factory()->for($this->user)->for($this->account)->for($this->category)->create();

        $this->patch("/planning/recurring/{$recurring->id}", [
            'type' => 'expense', 'account_id' => $trashed->id, 'category_id' => $this->category->id, 'amount' => 10,
            'frequency' => 'monthly', 'next_due_date' => '2026-11-01',
        ])->assertSessionHasErrors('account_id');
    }

    public function test_lists_and_reports_still_render_a_transaction_whose_account_is_trashed(): void
    {
        $orphan = Transaction::factory()->for($this->user)->for($this->account)->create(['category_id' => $this->category->id, 'date' => now()->toDateString()]);
        Account::whereKey($this->account->id)->update(['deleted_at' => now()]);

        $this->get('/finances/transactions')->assertOk();
        $this->get('/reports/custom')->assertOk();
        $this->assertSame($this->account->id, $orphan->fresh()->account->id);
    }

    public function test_deleting_an_account_whose_transactions_are_all_in_the_trash_archives_it_so_they_can_be_restored(): void
    {
        $transaction = Transaction::factory()->for($this->user)->for($this->account)->create(['category_id' => $this->category->id]);
        $this->delete("/finances/transactions/{$transaction->id}");

        $this->delete("/accounts/{$this->account->id}");

        $this->assertNotSoftDeleted('accounts', ['id' => $this->account->id]);
        $this->assertFalse($this->account->fresh()->is_active);
        $this->post("/settings/trash/transactions/{$transaction->id}/restore")->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_a_transaction_is_not_kept_when_recording_it_fails_halfway(): void
    {
        Event::listen(TransactionsMutated::class, fn () => throw new RuntimeException('listener failed'));

        try {
            app(TransactionService::class)->createExpense($this->user, $this->expense());
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Transaction::withTrashed()->count());
    }
}
