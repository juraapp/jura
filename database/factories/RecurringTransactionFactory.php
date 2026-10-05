<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    protected $model = RecurringTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory()->expense(),
            'type' => TransactionType::Expense,
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'description' => fake()->words(3, true),
            'frequency' => RecurringFrequency::Monthly,
            'next_due_date' => Carbon::today()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'day_of_month' => null,
            'is_active' => true,
            'end_date' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
            'category_id' => Category::factory()->income(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
