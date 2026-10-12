<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Category;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Bucket>
 */
class BucketFactory extends Factory
{
    protected $model = Bucket::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'kind' => BucketKind::Spending,
            'currency' => 'COP',
            'priority' => 100,
            'category_id' => Category::factory()->expense(),
            'cycle' => BudgetPeriodType::Monthly,
            'cap_amount' => fake()->randomFloat(2, 50000, 2000000),
            'carry_over' => CarryOverPolicy::Carry,
            'is_default' => false,
            'tracking_started_on' => Carbon::today()->toDateString(),
        ];
    }

    public function savings(): static
    {
        return $this->state(fn () => [
            'kind' => BucketKind::Savings,
            'category_id' => null,
            'cycle' => null,
            'cap_amount' => null,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['cycle' => BudgetPeriodType::Yearly]);
    }

    public function rule(FillRule $rule, string|int|float|null $value = null): static
    {
        return $this->state(fn () => ['fill_rule' => $rule, 'fill_value' => $value]);
    }

    public function priority(int $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }

    public function asDefault(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
