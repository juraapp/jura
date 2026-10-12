<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Scenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scenario>
 */
class ScenarioFactory extends Factory
{
    protected $model = Scenario::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'currency' => 'COP',
            'forecast_view' => ForecastView::Conservative,
            'horizon_months' => 12,
        ];
    }
}
