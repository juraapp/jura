<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Category;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\Budget;
use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetForecastMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function recreateLegacyTable(): void
    {
        Schema::create('budget_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->boolean('is_anticipated')->default(true);
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_legacy_forecasts_become_planned_expenses_and_the_table_is_dropped(): void
    {
        $this->recreateLegacyTable();
        $user = User::factory()->create();
        $budgetCategory = Category::factory()->expense()->create(['user_id' => $user->id]);
        $ownCategory = Category::factory()->expense()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->for($user)->create(['category_id' => $budgetCategory->id]);

        $row = fn (array $attributes) => DB::table('budget_forecasts')->insert($attributes + [
            'user_id' => $user->id, 'budget_id' => $budget->id, 'category_id' => null, 'description' => 'Forecast',
            'amount' => 120.5, 'date' => '2026-11-03', 'is_anticipated' => true, 'currency' => 'COP',
            'notes' => 'note', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row([]);
        $row(['category_id' => $ownCategory->id, 'is_anticipated' => false, 'description' => 'Maybe']);

        (require database_path('migrations/2026_10_12_100200_migrate_budget_forecasts_to_planned_events.php'))->up();

        $this->assertFalse(Schema::hasTable('budget_forecasts'));
        $events = PlannedEvent::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertCount(2, $events);

        $this->assertSame('expense', $events[0]->type->value);
        $this->assertSame($budgetCategory->id, $events[0]->category_id, 'falls back to the budget category');
        $this->assertSame(Certainty::Expected, $events[0]->certainty);
        $this->assertSame('120.50', $events[0]->amount->toDecimalString());
        $this->assertSame('2026-11-03', $events[0]->date->toDateString());
        $this->assertSame(PlannedEventStatus::Planned, $events[0]->status);
        $this->assertSame($user->id, $events[0]->user_id);

        $this->assertSame($ownCategory->id, $events[1]->category_id);
        $this->assertSame(Certainty::Speculative, $events[1]->certainty);
    }
}
