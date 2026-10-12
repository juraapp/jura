<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PlannedEvent>
 */
class PlannedEventFactory extends Factory
{
    protected $model = PlannedEvent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Expense,
            'description' => fake()->words(3, true),
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'currency' => 'COP',
            'date' => Carbon::now()->addDays(fake()->numberBetween(1, 60))->toDateString(),
            'account_id' => null,
            'category_id' => null,
            'certainty' => Certainty::Expected,
            'status' => PlannedEventStatus::Planned,
            'notes' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Income]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Expense]);
    }

    public function certainty(Certainty $certainty): static
    {
        return $this->state(fn () => ['certainty' => $certainty]);
    }

    public function status(PlannedEventStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
