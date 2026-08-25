<?php

namespace Database\Factories;

use App\Enums\SavingsGoalStatus;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    protected $model = SavingsGoal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'target_amount' => fake()->randomFloat(2, 100000, 10000000),
            'currency' => 'COP',
            'target_date' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'icon' => 'flag',
            'color' => '#2563eb',
            'status' => SavingsGoalStatus::Active,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SavingsGoalStatus::Completed]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SavingsGoalStatus::Archived]);
    }
}
