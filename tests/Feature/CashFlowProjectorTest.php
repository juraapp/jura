<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Services\CashFlowProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CashFlowProjectorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);

        $this->plan('income', 500000, '2026-11-05', Certainty::Confirmed);
        $this->plan('income', 300000, '2026-11-20', Certainty::Expected);
        $this->plan('income', 200000, '2026-11-25', Certainty::Speculative);
        $this->plan('expense', 100000, '2026-11-10', Certainty::Speculative);
        $this->plan('expense', 50000, '2026-12-03', Certainty::Confirmed);
        $this->plan('expense', 20000, '2026-10-10', Certainty::Expected); // overdue, counts in October
        $this->plan('income', 999999, '2026-11-06', Certainty::Confirmed, PlannedEventStatus::Realized);
        $this->plan('expense', 888888, '2026-11-07', Certainty::Confirmed, PlannedEventStatus::Skipped);
        $this->plan('income', 77, '2027-09-01', Certainty::Confirmed); // beyond the horizon
    }

    private function plan(string $type, float $amount, string $date, Certainty $certainty, PlannedEventStatus $status = PlannedEventStatus::Planned, string $currency = 'COP'): void
    {
        PlannedEvent::factory()->for($this->user)->create([
            'type' => $type,
            'amount' => $amount,
            'date' => $date,
            'certainty' => $certainty,
            'status' => $status,
            'currency' => $currency,
        ]);
    }

    private function months(ForecastView $view, int $count = 3): array
    {
        $projection = app(CashFlowProjector::class)->project($this->user, $view, $count);

        return $projection['COP']['months'];
    }

    public function test_conservative_view_counts_only_confirmed_income_but_all_expenses(): void
    {
        $months = $this->months(ForecastView::Conservative);

        $this->assertSame('2026-10', $months[0]['month']);
        $this->assertSame('0.00', $months[0]['income']->toDecimalString());
        $this->assertSame('20000.00', $months[0]['expense']->toDecimalString());
        $this->assertSame('980000.00', $months[0]['closing']->toDecimalString());

        $this->assertSame('500000.00', $months[1]['income']->toDecimalString());
        $this->assertSame('100000.00', $months[1]['expense']->toDecimalString());
        $this->assertSame('1380000.00', $months[1]['closing']->toDecimalString());

        $this->assertSame('50000.00', $months[2]['expense']->toDecimalString());
        $this->assertSame('1330000.00', $months[2]['closing']->toDecimalString());
    }

    public function test_expected_view_adds_expected_income(): void
    {
        $months = $this->months(ForecastView::Expected);

        $this->assertSame('800000.00', $months[1]['income']->toDecimalString());
        $this->assertSame('1680000.00', $months[1]['closing']->toDecimalString());
    }

    public function test_optimistic_view_adds_speculative_income(): void
    {
        $months = $this->months(ForecastView::Optimistic);

        $this->assertSame('1000000.00', $months[1]['income']->toDecimalString());
        $this->assertSame('1880000.00', $months[1]['closing']->toDecimalString());
    }

    public function test_opening_balance_is_the_current_net_worth_and_events_beyond_the_horizon_are_ignored(): void
    {
        $projection = app(CashFlowProjector::class)->project($this->user, ForecastView::Optimistic, 3);

        $this->assertSame('1000000.00', $projection['COP']['opening']->toDecimalString());
        $this->assertCount(3, $projection['COP']['months']);
    }

    public function test_currencies_are_projected_separately(): void
    {
        $this->plan('income', 100, '2026-11-02', Certainty::Confirmed, PlannedEventStatus::Planned, 'USD');

        $projection = app(CashFlowProjector::class)->project($this->user, ForecastView::Conservative, 3);

        $this->assertEqualsCanonicalizing(['COP', 'USD'], $projection->keys()->all());
        $this->assertSame('0.00', $projection['USD']['opening']->toDecimalString());
        $this->assertSame('100.00', $projection['USD']['months'][1]['closing']->toDecimalString());
    }
}
