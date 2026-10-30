<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Balances are cached per account; anything that changes what an account is worth must drop that cache.
 */
class BalanceCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 100]);
        $this->actingAs($this->user);
    }

    /**
     * @return array<string, string>
     */
    private function netWorth(): array
    {
        return app(Balances::class)->netWorthByCurrency($this->user->fresh())->map->toDecimalString()->all();
    }

    private function updateAccount(array $changes): void
    {
        $this->patch("/accounts/{$this->account->id}", array_merge([
            'name' => $this->account->name, 'type' => 'bank', 'initial_balance' => '100.00', 'currency' => 'COP', 'color' => '#000000', 'is_active' => true,
        ], $changes))->assertSessionHasNoErrors();
    }

    public function test_editing_the_initial_balance_updates_the_cached_balance(): void
    {
        $this->assertSame(['COP' => '100.00'], $this->netWorth());

        $this->updateAccount(['initial_balance' => 500]);

        $this->assertSame(['COP' => '500.00'], $this->netWorth());
        $this->assertSame('500.00', app(Balances::class)->accountBalance($this->account->fresh())->toDecimalString());
    }

    public function test_changing_currency_and_balance_does_not_relabel_the_old_balance(): void
    {
        $this->assertSame(['COP' => '100.00'], $this->netWorth());

        $this->updateAccount(['currency' => 'USD', 'initial_balance' => 7]);

        $this->assertSame(['USD' => '7.00'], $this->netWorth());
    }

    public function test_a_balance_cached_before_a_transaction_commits_is_dropped_on_commit(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $balances = app(Balances::class);

        DB::transaction(function () use ($category) {
            app(TransactionService::class)->createExpense($this->user, [
                'account_id' => $this->account->id, 'category_id' => $category->id, 'amount' => 30, 'date' => '2026-10-12',
            ]);

            // A concurrent request that read the balance before this commit caches the old value.
            Cache::put("account:{$this->account->id}:balance", '100.00');
        });

        $this->assertSame('70.00', $balances->accountBalance($this->account->fresh())->toDecimalString());
    }

    public function test_a_balance_cached_inside_a_transaction_that_rolls_back_is_dropped(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $balances = app(Balances::class);

        try {
            DB::transaction(function () use ($category, $balances) {
                app(TransactionService::class)->createExpense($this->user, [
                    'account_id' => $this->account->id, 'category_id' => $category->id, 'amount' => 30, 'date' => '2026-10-12',
                ]);

                // A listener reads (and caches) the balance that includes the uncommitted expense, then the write fails.
                $balances->accountBalance($this->account->fresh());

                throw new RuntimeException('write failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame('100.00', $balances->accountBalance($this->account->fresh())->toDecimalString());
    }
}
