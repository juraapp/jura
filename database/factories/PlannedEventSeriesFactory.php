<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Models\PlannedEventSeries;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PlannedEventSeries>
 */
class PlannedEventSeriesFactory extends Factory
{
    protected $model = PlannedEventSeries::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Income,
            'description' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 100000, 3000000),
            'currency' => 'COP',
            'account_id' => null,
            'category_id' => null,
            'certainty' => Certainty::Expected,
            'frequency' => RecurringFrequency::Monthly,
            'starts_on' => Carbon::now()->addDays(5)->toDateString(),
            'ends_on' => null,
        ];
    }
}
