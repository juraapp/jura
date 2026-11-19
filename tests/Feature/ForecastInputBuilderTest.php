<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\BucketCycle;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\ForecastInputBuilder;
use App\Modules\Planning\Services\ForecastInputs;
use App\Modules\Planning\Support\Forecast\EngineFlow;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastInputBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $checking;

    private Account $wallet;

    private Account $brokerage;

    private Bucket $groceries;

    private Bucket $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 12:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);

        $this->checking = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);
        $this->wallet = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 200000]);
        $this->brokerage = Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP', 'initial_balance' => 5000000]);
        Account::factory()->for($this->user)->inactive()->create(['currency' => 'COP', 'initial_balance' => 300000]);
        Account::factory()->for($this->user)->create(['currency' => 'USD', 'initial_balance' => 100]);

        $food = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->groceries = Bucket::factory()->for($this->user)->create([
            'name' => 'Groceries', 'currency' => 'COP', 'priority' => 0, 'category_id' => $food->id, 'cap_amount' => 300000,
            'fill_rule' => FillRule::Fixed, 'fill_value' => 250000, 'tracking_started_on' => '2026-10-01',
        ]);
        $this->trip = Bucket::factory()->savings()->for($this->user)->create(['name' => 'Trip', 'currency' => 'COP', 'priority' => 10, 'cap_amount' => 900000]);

        app(BucketLedger::class)->credit($this->groceries, Money::of(150000), AllocationSource::Adjustment, null, CarbonImmutable::parse('2026-11-01'));
        Transaction::factory()->for($this->user)->for($this->checking)->create(['type' => 'expense', 'category_id' => $food->id, 'amount' => 30000, 'date' => '2026-11-05']);
        BucketCycle::create(['bucket_id' => $this->groceries->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'status' => BucketCycle::OPEN, 'cap_override' => 400000]);
        BucketCycle::create(['bucket_id' => $this->groceries->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => BucketCycle::OPEN, 'cap_override' => 111]);

        $plan = fn (array $attributes) => PlannedEvent::factory()->for($this->user)->create($attributes + ['currency' => 'COP', 'account_id' => null, 'certainty' => 'confirmed']);
        $plan(['type' => 'income', 'amount' => 500000, 'date' => '2026-11-03', 'account_id' => $this->checking->id,
            'allocation_overrides' => [['bucket_id' => $this->groceries->id, 'amount' => '100000']]]);
        $plan(['type' => 'income', 'amount' => 300000, 'date' => '2026-11-20', 'certainty' => 'expected']);
        $plan(['type' => 'income', 'amount' => 200000, 'date' => '2026-11-25', 'account_id' => $this->checking->id, 'certainty' => 'speculative']);
        $plan(['type' => 'income', 'amount' => 400000, 'date' => '2026-11-15', 'account_id' => $this->brokerage->id]);
        $plan(['type' => 'expense', 'amount' => 50000, 'date' => '2026-11-12', 'account_id' => $this->checking->id, 'category_id' => $food->id]);
        $plan(['type' => 'expense', 'amount' => 70000, 'date' => '2026-11-13', 'account_id' => $this->brokerage->id]);
        $plan(['type' => 'transfer_out', 'amount' => 100000, 'date' => '2026-11-18', 'account_id' => $this->checking->id, 'counter_account_id' => $this->brokerage->id]);
        $plan(['type' => 'transfer_out', 'amount' => 60000, 'date' => '2026-11-19', 'account_id' => $this->brokerage->id, 'counter_account_id' => $this->wallet->id]);
        $plan(['type' => 'transfer_out', 'amount' => 10000, 'date' => '2026-11-21', 'account_id' => $this->checking->id, 'counter_account_id' => $this->wallet->id]);
        $plan(['type' => 'income', 'amount' => 999, 'date' => '2026-11-14', 'currency' => 'USD']);
        $plan(['type' => 'income', 'amount' => 888, 'date' => '2026-11-14', 'status' => 'realized']);
    }

    private function build(ForecastView $view = ForecastView::Expected): ForecastInputs
    {
        return app(ForecastInputBuilder::class)->build($this->user, 'COP', $view, CarbonImmutable::parse('2026-12-31'));
    }

    /**
     * @return list<string>
     */
    private function describe(ForecastInputs $inputs): array
    {
        return array_map(fn (EngineFlow $flow) => implode(' ', [
            $flow->date->toDateString(), $flow->kind->value, $flow->amount->toDecimalString(),
            implode(',', array_map(fn (Money $money) => $money->toDecimalString(), $flow->overrides)),
        ]), $inputs->flows);
    }

    public function test_start_cash_is_the_active_on_budget_accounts_of_the_currency(): void
    {
        // Checking 1,000,000 - 30,000 groceries + wallet 200,000; brokerage (off budget), the archived account and USD left out.
        $this->assertSame('1170000.00', $this->build()->startCash->toDecimalString());
    }

    public function test_flows_are_the_planned_money_that_moves_on_budget_cash(): void
    {
        $this->assertSame([
            '2026-11-10 income 500000.00 100000.00', // overdue: counts today, with its explicit split
            '2026-11-12 expense 50000.00 ',
            '2026-11-18 boundary -100000.00 ',       // checking -> off-budget brokerage
            '2026-11-19 boundary 60000.00 ',         // brokerage -> wallet
            '2026-11-20 income 300000.00 ',
        ], $this->describe($this->build()));

        $this->assertCount(4, $this->build(ForecastView::Conservative)->flows, 'the conservative view drops the expected income');
        $this->assertCount(6, $this->build(ForecastView::Optimistic)->flows, 'the optimistic view adds the speculative income');
    }

    public function test_coverage_counts_the_incomes_the_view_trusts(): void
    {
        $coverage = $this->build()->coverage;

        $this->assertSame([2, '800000.00', 3, '1000000.00', 1, 1, '400000.00'], [
            $coverage->incomesCounted, $coverage->countedAmount->toDecimalString(),
            $coverage->incomesTotal, $coverage->totalAmount->toDecimalString(),
            $coverage->overdueCounted, $coverage->offBudgetIncomes, $coverage->offBudgetAmount->toDecimalString(),
        ]);
    }

    public function test_buckets_carry_their_envelope_funding_and_cap_overrides(): void
    {
        $inputs = $this->build();
        [$groceries, $trip] = $inputs->buckets;

        $this->assertSame('120000.00', $groceries->envelope->toDecimalString(), '150,000 assigned - 30,000 spent');
        $this->assertTrue($groceries->isFunded);
        $this->assertSame('2026-11-01', $groceries->fundedSince->toDateString());
        $this->assertSame(['2026-11-01' => '400000.00'], array_map(fn (Money $cap) => $cap->toDecimalString(), $groceries->capOverrides));
        $this->assertSame('400000.00', $groceries->capOn(CarbonImmutable::parse('2026-11-20'))->toDecimalString());
        $this->assertSame('300000.00', $groceries->capOn(CarbonImmutable::parse('2026-12-05'))->toDecimalString());
        $this->assertFalse($trip->isFunded);
        $this->assertSame('0.00', $trip->envelope->toDecimalString());
        $this->assertSame('1050000.00', $inputs->startReadyToAssign()->toDecimalString());
    }

    public function test_the_cash_timeline_follows_the_flows(): void
    {
        $timeline = $this->build()->timeline;

        $this->assertSame('1620000.00', $timeline->at(CarbonImmutable::parse('2026-11-12'))->toDecimalString());
        // 1,170,000 + 500,000 - 50,000 - 100,000 + 60,000 + 300,000
        $this->assertSame('1880000.00', $timeline->at(CarbonImmutable::parse('2026-11-30'))->toDecimalString());
        $this->assertSame('1520000.00', $timeline->suffixMin(CarbonImmutable::parse('2026-11-11'))->toDecimalString());
    }

    public function test_budget_monthly_projects_on_budget_cash_without_interest(): void
    {
        $this->brokerage->update(['yield_rate' => 12, 'yield_compounding' => 'monthly']);
        $this->checking->update(['yield_rate' => 12, 'yield_compounding' => 'monthly']);

        $cop = app(CashFlowProjector::class)->budgetMonthly($this->user, ForecastView::Expected, 2)['COP'];

        $this->assertSame('1170000.00', $cop['opening']->toDecimalString());
        $this->assertSame(
            [['2026-11', '800000.00', '50000.00', '-40000.00', '1880000.00'], ['2026-12', '0.00', '0.00', '0.00', '1880000.00']],
            array_map(fn (array $month) => [
                $month['month'], $month['income']->toDecimalString(), $month['expense']->toDecimalString(),
                $month['boundary']->toDecimalString(), $month['closing']->toDecimalString(),
            ], $cop['months']),
        );
    }
}
