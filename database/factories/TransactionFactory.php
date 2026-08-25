<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'type' => TransactionType::Expense,
            'category_id' => Category::factory()->expense(),
            'amount' => fake()->randomFloat(2, 5000, 500000),
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'description' => fake()->words(3, true),
            'notes' => null,
            'payment_method' => null,
            'is_recurring_generated' => false,
            'recurring_transaction_id' => null,
            'transfer_group_id' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
            'category_id' => Category::factory()->income(),
        ]);
    }

    public function transferOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::TransferOut,
            'category_id' => null,
        ]);
    }

    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::TransferIn,
            'category_id' => null,
        ]);
    }
}
