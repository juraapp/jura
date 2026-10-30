<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Models\RecurringTransaction;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Amounts in different currencies are never treated as the same number: there is no exchange rate.
 */
class CurrencyGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cop;

    private Account $usd;

    private Category $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->cop = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $this->usd = Account::factory()->for($this->user)->create(['currency' => 'USD']);
        $this->expense = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    public function test_a_transfer_between_currencies_is_rejected(): void
    {
        $this->post('/finances/transactions', [
            'type' => 'transfer', 'from_account_id' => $this->cop->id, 'to_account_id' => $this->usd->id, 'amount' => 4000, 'date' => '2026-10-12',
        ])->assertSessionHasErrors(['to_account_id' => __('transactions.currency_mismatch')]);

        $this->assertSame(0, Transaction::count());
    }

    public function test_a_transaction_cannot_move_to_an_account_in_another_currency(): void
    {
        $transaction = Transaction::factory()->for($this->user)->for($this->cop)->create(['category_id' => $this->expense->id]);

        $this->patch("/finances/transactions/{$transaction->id}", [
            'account_id' => $this->usd->id, 'category_id' => $this->expense->id, 'amount' => 10, 'date' => '2026-10-12',
        ])->assertSessionHasErrors('account_id');

        $this->assertSame($this->cop->id, $transaction->fresh()->account_id);
    }

    public function test_bulk_move_leaves_transactions_in_another_currency_where_they_are(): void
    {
        $transaction = Transaction::factory()->for($this->user)->for($this->cop)->create(['category_id' => $this->expense->id]);

        $this->post('/finances/transactions/bulk-update', ['ids' => [$transaction->id], 'account_id' => $this->usd->id])->assertSessionHasNoErrors();

        $this->assertSame($this->cop->id, $transaction->fresh()->account_id);
    }

    public function test_an_account_with_transactions_keeps_its_currency(): void
    {
        Transaction::factory()->for($this->user)->for($this->cop)->create(['category_id' => $this->expense->id]);

        $this->patch("/accounts/{$this->cop->id}", [
            'name' => 'Main', 'type' => 'bank', 'initial_balance' => 0, 'currency' => 'USD', 'color' => '#000000',
        ])->assertSessionHasErrors(['currency' => __('accounts.currency_locked')]);

        $this->assertSame('COP', $this->cop->fresh()->currency);
    }

    /**
     * @return array<string, array{0: \Closure(User, Account, Category): mixed}>
     */
    public static function accountReferences(): array
    {
        return [
            'recurring template' => [fn (User $user, Account $account, Category $category) => RecurringTransaction::factory()->for($user)->for($account)->for($category)->create()],
            'planned event' => [fn (User $user, Account $account) => PlannedEvent::factory()->for($user)->create(['account_id' => $account->id, 'currency' => 'COP'])],
            'planned series' => [fn (User $user, Account $account) => PlannedEventSeries::factory()->for($user)->create(['account_id' => $account->id, 'currency' => 'COP'])],
            'bucket allowed accounts' => [fn (User $user, Account $account) => Bucket::factory()->savings()->for($user)->create(['currency' => 'COP', 'allowed_account_ids' => [$account->id]])],
            'wish-list allowed accounts' => [fn (User $user, Account $account) => WishlistItem::factory()->for($user)->create(['currency' => 'COP', 'allowed_account_ids' => [$account->id]])],
        ];
    }

    #[DataProvider('accountReferences')]
    public function test_an_account_used_by_plans_keeps_its_currency(\Closure $reference): void
    {
        $reference($this->user, $this->cop, $this->expense);

        $this->patch("/accounts/{$this->cop->id}", [
            'name' => 'Main', 'type' => 'bank', 'initial_balance' => 0, 'currency' => 'USD', 'color' => '#000000',
        ])->assertSessionHasErrors(['currency' => __('accounts.currency_locked')]);

        $this->assertSame('COP', $this->cop->fresh()->currency);
    }

    public function test_an_account_without_transactions_can_change_currency(): void
    {
        $this->patch("/accounts/{$this->cop->id}", [
            'name' => 'Main', 'type' => 'bank', 'initial_balance' => 0, 'currency' => 'EUR', 'color' => '#000000',
        ])->assertSessionHasNoErrors();

        $this->assertSame('EUR', $this->cop->fresh()->currency);
    }

    public function test_a_bucket_holding_money_keeps_its_currency(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Trip']);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => 100, 'currency' => 'COP']);

        $this->patch("/planning/buckets/{$bucket->id}", ['kind' => 'savings', 'name' => 'Trip', 'currency' => 'USD'])
            ->assertSessionHasErrors(['currency' => __('buckets.currency_locked')]);

        $this->assertSame('COP', $bucket->fresh()->currency);
    }

    public function test_a_bucket_only_accepts_accounts_in_its_currency(): void
    {
        $this->post('/planning/buckets', [
            'kind' => 'savings', 'name' => 'Trip', 'currency' => 'COP', 'allowed_account_ids' => [$this->usd->id],
        ])->assertSessionHasErrors('allowed_account_ids.0');

        $this->assertSame(0, Bucket::count());
    }

    public function test_a_plan_cannot_be_realized_into_an_account_in_another_currency(): void
    {
        $plan = PlannedEvent::factory()->for($this->user)->expense()->create(['currency' => 'COP']);

        $this->post("/planning/cash-flow/{$plan->id}/realize", ['account_id' => $this->usd->id, 'category_id' => $this->expense->id])
            ->assertSessionHasErrors('account_id');

        $this->assertSame(PlannedEventStatus::Planned, $plan->fresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_a_plan_cannot_be_linked_to_a_transaction_in_another_currency(): void
    {
        $plan = PlannedEvent::factory()->for($this->user)->expense()->create(['currency' => 'COP']);
        $transaction = Transaction::factory()->for($this->user)->for($this->usd)->create(['category_id' => $this->expense->id]);

        $this->post("/planning/cash-flow/{$plan->id}/link", ['transaction_id' => $transaction->id])->assertSessionHasErrors('transaction_id');

        $this->assertSame(PlannedEventStatus::Planned, $plan->fresh()->status);
    }
}
