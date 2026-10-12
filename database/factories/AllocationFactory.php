<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Allocation>
 */
class AllocationFactory extends Factory
{
    protected $model = Allocation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bucket_id' => Bucket::factory()->savings(),
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'currency' => 'COP',
            'date' => Carbon::today()->toDateString(),
            'source' => AllocationSource::Adjustment,
            'source_id' => null,
            'description' => null,
        ];
    }
}
