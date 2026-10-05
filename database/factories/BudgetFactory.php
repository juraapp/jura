<?php

namespace Database\Factories;

use App\Enums\BudgetPeriodType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory()->expense(),
            'amount' => fake()->randomFloat(2, 50000, 2000000),
            'allocated_amount' => null,
            'currency' => 'COP',
            'period_type' => BudgetPeriodType::Monthly,
            'period_start' => Carbon::now()->startOfMonth()->toDateString(),
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => BudgetPeriodType::Yearly,
            'period_start' => Carbon::now()->startOfYear()->toDateString(),
        ]);
    }
}
