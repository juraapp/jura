<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    protected $model = WishlistItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'bucket_id' => null,
            'name' => fake()->words(3, true),
            'price' => fake()->randomFloat(2, 50000, 5000000),
            'currency' => 'COP',
            'importance' => fake()->numberBetween(1, 5),
            'desired_by' => null,
            'status' => WishlistStatus::Wanted,
        ];
    }
}
