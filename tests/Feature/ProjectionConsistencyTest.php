<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Planning\Services\PlannedOccurrences;
use App\Modules\Planning\Services\PlannerService;
use App\Modules\Planning\Support\Forecast\EngineIncome;
use App\Modules\Planning\Support\ProjectedItem;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Every projection reads the same open planned money (PlannedOccurrences), to any horizon.
 */
class ProjectionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->user = User::factory()->create(['timezone' => 'UTC', 'currency_default' => 'COP']);
        $account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $service = app(PlannedEventService::class);

        $series = fn (array $attributes) => PlannedEventSeries::factory()->for($this->user)->create($attributes + [
            'type' => 'income', 'currency' => 'COP', 'frequency' => RecurringFrequency::Monthly, 'certainty' => 'confirmed', 'account_id' => $account->id,
        ]);

        $service->createSeries($this->user, ['type' => 'income', 'description' => 'Salary', 'amount' => 1000, 'date' => '2026-11-25', 'frequency' => 'monthly', 'certainty' => 'confirmed', 'account_id' => $account->id]);
        $service->createSeries($this->user, ['type' => 'income', 'description' => 'Rent in', 'amount' => 300, 'date' => '2026-11-15', 'frequency' => 'monthly', 'certainty' => 'expected', 'account_id' => $account->id, 'auto_post' => true]);
        $paused = $series(['amount' => 50, 'starts_on' => '2026-11-20']);
        $service->materialize($paused, Carbon::parse('2027-12-31'));
        $service->pauseSeries($paused);
        $series(['amount' => 70, 'starts_on' => '2026-01-05', 'ends_on' => '2026-10-31']);
        $service->createEvent($this->user, ['type' => 'income', 'description' => 'Bonus', 'amount' => 500, 'date' => '2027-06-01', 'certainty' => 'speculative']);
        PlannedEvent::factory()->for($this->user)->income()->create(['amount' => 40, 'currency' => 'COP', 'date' => '2026-10-20', 'certainty' => 'confirmed']);
        PlannedEvent::factory()->for($this->user)->expense()->create(['amount' => 20, 'currency' => 'COP', 'date' => '2026-12-03']);
    }

    private function sum(iterable $amounts): string
    {
        $total = Money::zero();
        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }

        return $total->toDecimalString();
    }

    public function test_cash_flow_income_equals_the_open_occurrences_for_the_same_window_and_currency(): void
    {
        $months = 24;
        $end = Carbon::parse('2026-11-01')->addMonthsNoOverflow($months)->subDay();
        $items = app(PlannedOccurrences::class)->open($this->user, $end, 'COP');

        $projection = app(CashFlowProjector::class)->project($this->user, ForecastView::Optimistic, $months)->get('COP');

        $this->assertSame(
            $this->sum($items->filter(fn (ProjectedItem $item) => $item->isIncome())->pluck('amount')),
            $this->sum(collect($projection['months'])->pluck('income')),
        );
        $this->assertSame(
            $this->sum($items->filter(fn (ProjectedItem $item) => $item->isExpense())->pluck('amount')),
            $this->sum(collect($projection['months'])->pluck('expense')),
        );
        $this->assertSame('40.00', $projection['months'][0]['income']->subtract(Money::of(1300))->toDecimalString(), 'the overdue income counts this month');
    }

    public function test_paused_and_ended_series_add_nothing_beyond_what_is_stored(): void
    {
        $items = app(PlannedOccurrences::class)->open($this->user, Carbon::parse('2028-12-31'), 'COP', TransactionType::Income);

        $this->assertSame(0, $items->filter(fn (ProjectedItem $item) => $item->amount->toDecimalString() === '70.00')->count());
        $this->assertSame(0, $items->filter(fn (ProjectedItem $item) => $item->amount->toDecimalString() === '50.00')->count());
    }

    public function test_the_planner_sees_the_same_incomes(): void
    {
        $context = app(PlannerService::class)->context($this->user, 'COP', ForecastView::Optimistic, 24);
        $end = Carbon::instance($this->user->today())->addMonthsNoOverflow(24);
        $items = app(PlannedOccurrences::class)->open($this->user, $end, 'COP', TransactionType::Income);

        $this->assertSame($this->sum($items->pluck('amount')), $this->sum(array_map(fn (EngineIncome $income) => $income->amount, $context->input->incomes)));
    }

    public function test_a_24_month_horizon_includes_months_13_to_24(): void
    {
        $projection = app(CashFlowProjector::class)->project($this->user, ForecastView::Conservative, 24)->get('COP');

        $this->assertCount(24, $projection['months']);
        foreach (array_slice($projection['months'], 12) as $month) {
            $this->assertSame('1000.00', $month['income']->toDecimalString(), "salary in {$month['month']}");
        }
    }
}
