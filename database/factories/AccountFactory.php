<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'institution' => fake()->company(),
            'initial_balance' => fake()->randomFloat(2, 0, 5000000),
            'currency' => 'COP',
            'color' => '#2563eb',
            'icon' => 'wallet',
            'masked_number' => null,
            'is_active' => true,
            'on_budget' => true,
            'notes' => null,
        ];
    }

    /**
     * An account kept out of the budget (its money never counts toward ready to assign).
     */
    public function offBudget(): static
    {
        return $this->state(fn (array $attributes) => [
            'on_budget' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
