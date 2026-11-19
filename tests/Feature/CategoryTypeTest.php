<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An income is only ever filed under an income category and an expense under an expense category.
 */
class CategoryTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $incomeCategory;

    private Category $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $this->incomeCategory = Category::factory()->income()->create(['user_id' => $this->user->id]);
        $this->expenseCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    public function test_a_transaction_needs_a_category_of_its_own_type(): void
    {
        $this->post('/finances/transactions', [
            'type' => 'income', 'account_id' => $this->account->id, 'category_id' => $this->expenseCategory->id, 'amount' => 10, 'date' => '2026-10-12',
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_editing_a_transaction_keeps_the_category_type(): void
    {
        $income = Transaction::factory()->for($this->user)->for($this->account)->income()->create(['category_id' => $this->incomeCategory->id]);

        $this->patch("/finances/transactions/{$income->id}", [
            'account_id' => $this->account->id, 'category_id' => $this->expenseCategory->id, 'amount' => 10, 'date' => '2026-10-12',
        ])->assertSessionHasErrors('category_id');

        $this->assertSame($this->incomeCategory->id, $income->fresh()->category_id);
    }

    public function test_a_planned_event_needs_a_category_of_its_own_type(): void
    {
        $this->post('/planning/cash-flow', [
            'type' => 'expense', 'description' => 'Rent', 'amount' => 10, 'date' => '2026-11-01', 'certainty' => 'expected',
            'category_id' => $this->incomeCategory->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(0, PlannedEvent::count());
    }

    public function test_realizing_a_plan_needs_a_category_of_the_plans_type(): void
    {
        $plan = PlannedEvent::factory()->for($this->user)->income()->create();

        $this->post("/planning/cash-flow/{$plan->id}/realize", [
            'account_id' => $this->account->id, 'category_id' => $this->expenseCategory->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(0, Transaction::count());
    }
}
