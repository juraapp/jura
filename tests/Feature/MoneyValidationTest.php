<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every money input shares one rule: at most two decimals, no exponent, and within decimal(15,2).
 */
class MoneyValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $this->actingAs($this->user);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'exponent' => ['1e3'],
            'sub-cent' => ['0.001'],
            'above decimal(15,2)' => ['100000000000000'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_a_transaction_amount_is_rejected(string $amount): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $this->post('/finances/transactions', [
            'type' => 'expense', 'account_id' => $this->account->id, 'category_id' => $category->id, 'amount' => $amount, 'date' => '2026-10-12',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, Transaction::count());
    }

    #[DataProvider('invalidAmounts')]
    public function test_a_transfer_amount_is_rejected(string $amount): void
    {
        $other = Account::factory()->for($this->user)->create(['currency' => 'COP']);

        $this->post('/finances/transactions', [
            'type' => 'transfer', 'from_account_id' => $this->account->id, 'to_account_id' => $other->id, 'amount' => $amount, 'date' => '2026-10-12',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, Transaction::count());
    }

    #[DataProvider('invalidAmounts')]
    public function test_an_initial_balance_is_rejected(string $amount): void
    {
        $this->post('/accounts', ['name' => 'New', 'type' => 'bank', 'initial_balance' => $amount, 'currency' => 'COP'])
            ->assertSessionHasErrors('initial_balance');
    }

    public function test_a_negative_initial_balance_with_cents_is_accepted(): void
    {
        $this->post('/accounts', ['name' => 'Card', 'type' => 'credit_card', 'initial_balance' => '-1250.50', 'currency' => 'COP', 'color' => '#000000'])
            ->assertSessionHasNoErrors();

        $this->assertSame('-1250.50', Account::where('name', 'Card')->sole()->initial_balance->toDecimalString());
    }

    #[DataProvider('invalidAmounts')]
    public function test_planning_amounts_are_rejected(string $amount): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);

        $this->post("/planning/buckets/{$bucket->id}/adjust", ['amount' => $amount])->assertSessionHasErrors('amount');
        $this->post('/planning/buckets', ['kind' => 'savings', 'name' => 'Trip', 'currency' => 'COP', 'cap_amount' => $amount])
            ->assertSessionHasErrors('cap_amount');
        $this->post('/planning/cash-flow', [
            'type' => 'income', 'description' => 'Salary', 'amount' => $amount, 'date' => '2026-11-01', 'certainty' => 'expected',
        ])->assertSessionHasErrors('amount');
        $this->post('/planning/wishlist', ['name' => 'Phone', 'price' => $amount, 'currency' => 'COP'])->assertSessionHasErrors('price');
    }
}
