<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'parent_id' => null,
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(CategoryType::cases()),
            'icon' => 'tag',
            'color' => '#6b7280',
            'is_system' => false,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => ['type' => CategoryType::Expense]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => ['type' => CategoryType::Income]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null, 'is_system' => true]);
    }
}
