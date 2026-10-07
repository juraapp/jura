<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BudgetForecast>
 */
class BudgetForecastFactory extends Factory
{
    protected $model = \App\Models\BudgetForecast::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'category_id' => Category::factory()->expense(),
            'description' => fake()->words(3, true),
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'date' => Carbon::now()->addDays(fake()->numberBetween(-30, 60))->toDateString(),
            'is_anticipated' => true,
            'currency' => 'COP',
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
