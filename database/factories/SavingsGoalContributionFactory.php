<?php

namespace Database\Factories;

use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoalContribution>
 */
class SavingsGoalContributionFactory extends Factory
{
    protected $model = SavingsGoalContribution::class;

    public function definition(): array
    {
        return [
            'savings_goal_id' => SavingsGoal::factory(),
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'linked_transaction_id' => null,
            'notes' => null,
        ];
    }
}
