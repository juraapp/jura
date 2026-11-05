<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\BucketProjection;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\PlannerService;
use App\Modules\Planning\Support\Forecast\EngineResult;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Projected ready to assign must reconcile with two pieces computed elsewhere: the on-budget cash month by month
 * (CashFlowProjector::budgetMonthly, written without the forecast builder) and what funded envelopes hold
 * (BucketProjection). At every month end: engine cash = projected on-budget cash, and engine ready to assign =
 * projected cash − projected earmarks. Today it must equal the money map. The first month is also checked against
 * hand-computed numbers.
 */
class FreeMoneyReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const MONTHS = 6;

    private User $user;

    private Account $checking;

    private WishlistItem $laptop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 12:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);

        $this->checking = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 2000000]);
        $wallet = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 100000]);
        $brokerage = Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP', 'initial_balance' => 3000000]);
        Account::factory()->for($this->user)->inactive()->create(['currency' => 'COP', 'initial_balance' => 777000]);
        $food = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $groceries = Bucket::factory()->for($this->user)->create([
            'name' => 'Groceries', 'currency' => 'COP', 'priority' => 0, 'kind' => BucketKind::Spending, 'category_id' => $food->id,
            'cycle' => 'monthly', 'fill_rule' => FillRule::FillToTarget, 'cap_amount' => 600000, 'carry_over' => CarryOverPolicy::Release,
            'tracking_started_on' => '2026-10-01',
        ]);
        $emergency = Bucket::factory()->savings()->for($this->user)->create([
            'name' => 'Emergency', 'currency' => 'COP', 'priority' => 10, 'fill_rule' => FillRule::PercentOfRemaining, 'fill_value' => 50,
            'cap_amount' => 2000000, 'tracking_started_on' => '2026-10-01',
        ]);
        Bucket::factory()->for($this->user)->create([
            'name' => 'Fun', 'currency' => 'COP', 'priority' => 5, 'kind' => BucketKind::Spending, 'category_id' => null,
            'cycle' => 'monthly', 'fill_rule' => FillRule::Fixed, 'fill_value' => 100000, 'cap_amount' => null,
            'carry_over' => CarryOverPolicy::Overflow, 'overflow_bucket_id' => $emergency->id, 'tracking_started_on' => '2026-10-01',
        ]);

        $ledger = app(BucketLedger::class);
        $ledger->credit($groceries, Money::of(400000), AllocationSource::Adjustment, null, CarbonImmutable::parse('2026-11-01'));
        $ledger->credit($emergency, Money::of(500000), AllocationSource::Adjustment, null, CarbonImmutable::parse('2026-11-01'));
        Transaction::factory()->for($this->user)->for($this->checking)->create(['type' => 'expense', 'category_id' => $food->id, 'amount' => 150000, 'date' => '2026-11-05']);

        PlannedEventSeries::factory()->for($this->user)->create([
            'type' => 'income', 'amount' => 1500000, 'currency' => 'COP', 'account_id' => $this->checking->id, 'certainty' => 'confirmed',
            'frequency' => RecurringFrequency::Monthly, 'starts_on' => '2026-11-15',
        ]);
        PlannedEventSeries::factory()->for($this->user)->create([
            'type' => 'expense', 'amount' => 200000, 'currency' => 'COP', 'account_id' => $this->checking->id, 'category_id' => $food->id,
            'certainty' => 'confirmed', 'frequency' => RecurringFrequency::Monthly, 'starts_on' => '2026-11-20',
        ]);
        $plan = fn (array $attributes) => PlannedEvent::factory()->for($this->user)->create($attributes + ['currency' => 'COP', 'account_id' => null, 'certainty' => 'confirmed']);
        $plan(['type' => 'income', 'amount' => 400000, 'date' => '2026-12-20', 'certainty' => 'expected']);
        $plan(['type' => 'income', 'amount' => 300000, 'date' => '2027-01-10', 'certainty' => 'speculative']);
        $plan(['type' => 'income', 'amount' => 80000, 'date' => '2026-11-02', 'account_id' => $wallet->id, 'certainty' => 'expected']); // overdue
        $plan(['type' => 'expense', 'amount' => 900000, 'date' => '2027-02-01', 'account_id' => $this->checking->id]);
        $plan(['type' => 'expense', 'amount' => 60000, 'date' => '2026-12-12', 'account_id' => $brokerage->id]);
        $plan(['type' => 'transfer_out', 'amount' => 250000, 'date' => '2026-12-05', 'account_id' => $this->checking->id, 'counter_account_id' => $brokerage->id]);
        $plan(['type' => 'transfer_out', 'amount' => 50000, 'date' => '2027-01-05', 'account_id' => $brokerage->id, 'counter_account_id' => $wallet->id]);
        $plan(['type' => 'transfer_out', 'amount' => 30000, 'date' => '2027-01-06', 'account_id' => $this->checking->id, 'counter_account_id' => $wallet->id]);
        $plan(['type' => 'income', 'amount' => 999000, 'date' => '2026-12-01', 'account_id' => $brokerage->id]);
        $plan(['type' => 'income', 'amount' => 5, 'date' => '2026-12-01', 'currency' => 'USD']);

        $this->laptop = WishlistItem::factory()->for($this->user)->create(['name' => 'Laptop', 'price' => 4000000, 'currency' => 'COP', 'bucket_id' => null]);
    }

    private function engine(ForecastView $view, bool $withLaptop = false): EngineResult
    {
        $planner = app(PlannerService::class);
        $context = $planner->context($this->user, 'COP', $view, self::MONTHS);
        $lines = $withLaptop ? $planner->lines($this->user, 'COP', $context, [['item_id' => $this->laptop->id]]) : [];

        return app(ForecastEngine::class)->run($context->input, $lines);
    }

    /**
     * @return list<array{month: string, end: CarbonImmutable, closing: Money}>
     */
    private function cashMonths(ForecastView $view): array
    {
        return array_map(fn (array $month) => [
            'month' => $month['month'],
            'end' => CarbonImmutable::parse($month['month'].'-01')->endOfMonth()->startOfDay(),
            'closing' => $month['closing'],
        ], app(CashFlowProjector::class)->budgetMonthly($this->user, $view, self::MONTHS)['COP']['months']);
    }

    public function test_today_ready_to_assign_is_the_money_map(): void
    {
        // Cash: checking 2,000,000 - 150,000 + wallet 100,000. Earmarked: groceries 400,000 - 150,000 + emergency 500,000.
        $this->assertSame('1200000.00', app(MoneyMap::class)->readyToAssign($this->user, 'COP')->toDecimalString());

        foreach (ForecastView::cases() as $view) {
            $context = app(PlannerService::class)->context($this->user, 'COP', $view, self::MONTHS);
            $this->assertSame('1200000.00', $context->startReadyToAssign->toDecimalString(), $view->value);
            $this->assertSame('1950000.00', $context->input->startCash->toDecimalString(), $view->value);
        }
    }

    public function test_every_month_end_reconciles_cash_and_earmarks_in_every_view(): void
    {
        foreach (ForecastView::cases() as $view) {
            $result = $this->engine($view);
            $months = $this->cashMonths($view);
            $earmarks = app(BucketProjection::class)->earmarksAt($this->user, 'COP', $view, array_column($months, 'end'));

            $this->assertCount(self::MONTHS, $months);
            foreach ($months as $month) {
                $point = $result->pointAt($month['end']);
                $label = "{$view->value} {$month['month']}";

                $this->assertSame($month['closing']->toDecimalString(), $point->cash->toDecimalString(), "cash {$label}");
                $this->assertSame(
                    $month['closing']->subtract($earmarks[$month['end']->format('Y-m-d')])->toDecimalString(),
                    $point->free->toDecimalString(),
                    "ready to assign {$label}",
                );
            }
        }
    }

    public function test_the_first_month_end_matches_the_hand_computed_figures(): void
    {
        // Expected view, Nov 30. Cash: 1,950,000 + 80,000 (overdue wallet income, today) + 1,500,000 (Nov 15) - 200,000 (Nov 20)
        // = 3,330,000. Nov 10: the 80,000 fills groceries' room (600,000 - 250,000 = 350,000) with 80,000. Nov 15:
        // groceries takes 270,000 (full), Fun 100,000, Emergency half of the 1,130,000 left = 565,000 (1,065,000).
        // Nov 20: groceries spends 200,000 (400,000). Nov 30: groceries releases 400,000, Fun's 100,000 overflows into
        // Emergency: 1,165,000 earmarked, 2,165,000 free.
        $point = $this->engine(ForecastView::Expected)->pointAt(CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(['3330000.00', '1165000.00', '2165000.00'], [
            $point->cash->toDecimalString(), $point->earmarked->toDecimalString(), $point->free->toDecimalString(),
        ]);
        $this->assertSame('1165000.00', app(BucketProjection::class)->earmarksAt($this->user, 'COP', ForecastView::Expected, [CarbonImmutable::parse('2026-11-30')])['2026-11-30']->toDecimalString());
    }

    public function test_with_a_purchase_cash_is_the_projected_cash_less_what_was_bought(): void
    {
        foreach (ForecastView::cases() as $view) {
            $result = $this->engine($view, withLaptop: true);
            $bought = $result->lines[0]->purchaseDate;
            $this->assertNotNull($bought, $view->value);

            foreach ($this->cashMonths($view) as $month) {
                $spent = $month['end']->gte($bought) ? Money::of(4000000) : Money::zero();
                $this->assertSame(
                    $month['closing']->subtract($spent)->toDecimalString(),
                    $result->pointAt($month['end'])->cash->toDecimalString(),
                    "{$view->value} {$month['month']}",
                );
                $this->assertFalse($result->pointAt($month['end'])->cash->isNegative());
            }
        }
    }
}
