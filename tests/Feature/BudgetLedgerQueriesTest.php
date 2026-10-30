<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What the ledger tells Planning about budgeted money: which accounts count and what they spent.
 */
class BudgetLedgerQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_balances_only_cover_active_on_budget_accounts(): void
    {
        $user = User::factory()->create();
        $budgeted = Account::factory()->for($user)->create(['currency' => 'COP', 'initial_balance' => 100]);
        Account::factory()->for($user)->offBudget()->create(['initial_balance' => 500]);
        $archived = Account::factory()->for($user)->inactive()->create(['initial_balance' => 700]);
        Account::factory()->for($user)->create(['initial_balance' => 900])->delete();

        $balances = app(Balances::class)->budgetBalances($user);

        $this->assertSame([$budgeted->id], $balances->pluck('id')->all());
        $this->assertSame('100.00', $balances->first()['balance']->toDecimalString());
        $this->assertEqualsCanonicalizing([$budgeted->id, $archived->id], app(LedgerQuery::class)->onBudgetAccountIds($user->id));
        $this->assertTrue(app(LedgerQuery::class)->isOnBudget($user->id, $budgeted->id));
    }

    public function test_expense_total_can_be_open_ended_and_limited_to_on_budget_accounts(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $budgeted = Account::factory()->for($user)->create(['currency' => 'COP']);
        $offBudget = Account::factory()->for($user)->offBudget()->create(['currency' => 'COP']);
        $spend = fn (Account $account, int $amount, string $date) => Transaction::factory()->for($user)->for($account)->create([
            'category_id' => $category->id, 'amount' => $amount, 'date' => $date,
        ]);
        $spend($budgeted, 100, '2026-10-01');
        $spend($budgeted, 40, '2027-01-01');
        $spend($offBudget, 7, '2026-10-02');

        $ledger = app(LedgerQuery::class);
        $from = Carbon::parse('2026-10-01');

        $this->assertSame('107.00', $ledger->expenseTotal($user->id, $category->id, 'COP', $from, Carbon::parse('2026-10-31'))->toDecimalString());
        $this->assertSame('147.00', $ledger->expenseTotal($user->id, $category->id, 'COP', $from, null)->toDecimalString());
        $this->assertSame('140.00', $ledger->expenseTotal($user->id, $category->id, 'COP', $from, null, onBudgetOnly: true)->toDecimalString());
    }
}
