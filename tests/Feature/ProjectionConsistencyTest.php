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
 * Every projection reads the same open planned money (PlannedOccurrences), to any horizon. Expected figures
 * are worked out by hand from the plans in setUp.
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

    /**
     * Today is 2026-11-10; the window is Nov 2026 to Oct 2028 (24 months). Income: salary 24 x 1000 = 24000,
     * rent 24 x 300 = 7200, the bonus 500 and the overdue 40 (counted this month) = 31740. Paused and ended
     * series add nothing. Expense: the 20 on 2026-12-03.
     */
    public function test_cash_flow_counts_every_open_occurrence_to_any_horizon(): void
    {
        $projection = app(CashFlowProjector::class)->project($this->user, ForecastView::Optimistic, 24)->get('COP');

        $this->assertSame('31740.00', $this->sum(collect($projection['months'])->pluck('income')));
        $this->assertSame('20.00', $this->sum(collect($projection['months'])->pluck('expense')));
        $this->assertSame('1340.00', $projection['months'][0]['income']->toDecimalString(), 'salary, rent and the overdue income');
        $this->assertSame('20.00', $projection['months'][1]['expense']->toDecimalString());
        $this->assertSame('1800.00', $projection['months'][7]['income']->toDecimalString(), 'June 2027 adds the bonus');
    }

    public function test_paused_and_ended_series_add_nothing_beyond_what_is_stored(): void
    {
        $items = app(PlannedOccurrences::class)->open($this->user, Carbon::parse('2028-12-31'), 'COP', TransactionType::Income);

        $this->assertSame(0, $items->filter(fn (ProjectedItem $item) => $item->amount->toDecimalString() === '70.00')->count());
        $this->assertSame(0, $items->filter(fn (ProjectedItem $item) => $item->amount->toDecimalString() === '50.00')->count());
    }

    /**
     * The planner looks 24 months ahead from 2026-11-10 (to 2028-11-10): salary on the 25th and rent on the
     * 15th from Nov 2026 to Oct 2028 (24 each; the November 2028 dates are after the horizon), plus the bonus
     * and the overdue 40: 24000 + 7200 + 500 + 40 = 31740 over 50 incomes.
     */
    public function test_the_planner_sees_the_same_incomes(): void
    {
        $context = app(PlannerService::class)->context($this->user, 'COP', ForecastView::Optimistic, 24);

        $this->assertCount(50, $context->input->incomes);
        $this->assertSame('31740.00', $this->sum(array_map(fn (EngineIncome $income) => $income->amount, $context->input->incomes)));
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
