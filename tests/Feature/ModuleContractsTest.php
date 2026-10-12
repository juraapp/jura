<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Policies\AccountPolicy;
use App\Modules\Ledger\Policies\CategoryPolicy;
use App\Modules\Ledger\Policies\TransactionPolicy;
use App\Modules\Ledger\Services\BalanceCalculator;
use App\Modules\Ledger\Services\EloquentLedgerQuery;
use App\Modules\Ledger\Services\TransactionService;
use App\Modules\Planning\Contracts\BudgetProgress;
use App\Modules\Planning\Models\Budget;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\RecurringTransaction;
use App\Modules\Planning\Models\SavingsGoal;
use App\Modules\Planning\Policies\BudgetPolicy;
use App\Modules\Planning\Policies\PlannedEventPolicy;
use App\Modules\Planning\Policies\RecurringTransactionPolicy;
use App\Modules\Planning\Policies\SavingsGoalPolicy;
use App\Modules\Planning\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ModuleContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_contracts_resolve_to_their_implementations(): void
    {
        $this->assertInstanceOf(BalanceCalculator::class, app(Balances::class));
        $this->assertInstanceOf(EloquentLedgerQuery::class, app(LedgerQuery::class));
        $this->assertInstanceOf(TransactionService::class, app(RecordsTransactions::class));
    }

    public function test_ledger_models_resolve_their_policies(): void
    {
        $this->assertInstanceOf(AccountPolicy::class, Gate::getPolicyFor(Account::class));
        $this->assertInstanceOf(CategoryPolicy::class, Gate::getPolicyFor(Category::class));
        $this->assertInstanceOf(TransactionPolicy::class, Gate::getPolicyFor(Transaction::class));
    }

    public function test_expense_total_sums_only_the_matching_category_currency_and_period(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $otherCategory = Category::factory()->expense()->create(['user_id' => $user->id]);
        $cop = Account::factory()->for($user)->create(['currency' => 'COP']);
        $usd = Account::factory()->for($user)->create(['currency' => 'USD']);

        $make = fn (Account $account, Category $cat, string $date, float $amount) => Transaction::factory()
            ->for($user)->for($account)->create(['category_id' => $cat->id, 'date' => $date, 'amount' => $amount]);

        $make($cop, $category, '2026-10-05', 100.50);
        $make($cop, $category, '2026-10-20', 50.00);
        $make($cop, $category, '2026-09-30', 999.00); // outside the period
        $make($usd, $category, '2026-10-06', 777.00); // other currency
        $make($cop, $otherCategory, '2026-10-07', 555.00); // other category

        $total = app(LedgerQuery::class)->expenseTotal(
            $category->id,
            'COP',
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame('150.50', $total->toDecimalString());
    }

    public function test_account_and_category_options_are_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Account::factory()->for($user)->create(['name' => 'Active', 'is_active' => true]);
        Account::factory()->for($user)->inactive()->create(['name' => 'Archived']);
        Account::factory()->create(['name' => 'Someone else']);
        Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Mine']);

        $ledger = app(LedgerQuery::class);

        $this->assertSame(['Active'], $ledger->activeAccountOptions()->pluck('name')->all());
        $this->assertContains('Mine', $ledger->categoryOptions(CategoryType::Expense)->pluck('name')->all());
        $this->assertNotContains('Mine', $ledger->categoryOptions(CategoryType::Income)->pluck('name')->all());
    }

    public function test_record_creates_a_transaction_and_returns_its_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->income()->create(['user_id' => $user->id]);

        $id = app(RecordsTransactions::class)->record($user, TransactionType::Income, [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 1500.25,
            'date' => '2026-10-12',
            'description' => 'Freelance',
        ]);

        $transaction = Transaction::findOrFail($id);
        $this->assertSame(TransactionType::Income, $transaction->type);
        $this->assertSame('1500.25', $transaction->amount->toDecimalString());
        $this->assertFalse($transaction->is_recurring_generated);
    }

    public function test_planning_contract_resolves_to_its_implementation(): void
    {
        $this->assertInstanceOf(BudgetService::class, app(BudgetProgress::class));
    }

    public function test_planning_models_resolve_their_policies(): void
    {
        $this->assertInstanceOf(BudgetPolicy::class, Gate::getPolicyFor(Budget::class));
        $this->assertInstanceOf(PlannedEventPolicy::class, Gate::getPolicyFor(PlannedEvent::class));
        $this->assertInstanceOf(SavingsGoalPolicy::class, Gate::getPolicyFor(SavingsGoal::class));
        $this->assertInstanceOf(RecurringTransactionPolicy::class, Gate::getPolicyFor(RecurringTransaction::class));
    }
}
