<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanningReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000, 'created_at' => '2026-01-01']);
    }

    private function plan(string $type, int $amount, string $date, Certainty $certainty): void
    {
        PlannedEvent::factory()->for($this->user)->create([
            'type' => $type, 'amount' => $amount, 'currency' => 'COP', 'date' => $date, 'certainty' => $certainty,
        ]);
    }

    /* ---------- forecast report ---------- */

    public function test_the_forecast_report_separates_recorded_history_from_projections_in_three_views(): void
    {
        $this->plan('income', 500000, '2026-11-05', Certainty::Confirmed);
        $this->plan('income', 300000, '2026-11-20', Certainty::Expected);
        $this->plan('income', 200000, '2026-11-25', Certainty::Speculative);
        $this->plan('expense', 100000, '2026-12-03', Certainty::Confirmed);

        $this->get('/reports/forecast')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Forecast')
            ->has('currencies', 1)
            ->where('currencies.0.currency', 'COP')
            ->where('currencies.0.opening', 1000000)
            ->has('currencies.0.actual', 6)
            ->has('currencies.0.projected.conservative', 6)
            ->where('currencies.0.projected.conservative.0.month', '2026-11')
            ->where('currencies.0.projected.conservative.0.closing', 1500000)
            ->where('currencies.0.projected.expected.0.closing', 1800000)
            ->where('currencies.0.projected.optimistic.0.closing', 2000000)
            ->where('currencies.0.projected.conservative.1.closing', 1400000)
        );
    }

    public function test_the_forecast_report_requires_login(): void
    {
        auth()->logout();

        $this->get('/reports/forecast')->assertRedirect('/login');
    }

    /* ---------- buckets report ---------- */

    public function test_the_buckets_report_shows_balances_monthly_movements_and_projected_balances(): void
    {
        $car = Bucket::factory()->savings()->for($this->user)->priority(10)->rule(FillRule::Fixed, 400000)->create(['name' => 'Car', 'currency' => 'COP', 'cap_amount' => 5000000]);
        Bucket::factory()->savings()->for($this->user)->asDefault()->create(['name' => 'Unallocated', 'currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($car)->create(['amount' => 1000000, 'currency' => 'COP', 'date' => '2026-08-10', 'source' => AllocationSource::Income]);
        Allocation::factory()->for($this->user)->for($car)->create(['amount' => -250000, 'currency' => 'COP', 'date' => '2026-10-02', 'source' => AllocationSource::Spend]);
        Allocation::factory()->create(['amount' => 777, 'currency' => 'COP']); // someone else's
        $this->plan('income', 1000000, '2026-11-05', Certainty::Confirmed);

        $this->get('/reports/buckets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Buckets')
            ->has('currencies', 1)
            ->where('currencies.0.currency', 'COP')
            ->where('currencies.0.buckets.0.name', 'Car')
            ->where('currencies.0.buckets.0.available', 750000)
            ->where('currencies.0.buckets.0.cap', 5000000)
            ->has('currencies.0.history.labels', 6)
            ->where('currencies.0.history.series.0.name', 'Car')
            ->where('currencies.0.history.series.0.data.5', -250000)
            ->where('currencies.0.history.series.0.data.3', 1000000)
            ->where('currencies.0.projected.0.name', 'Car')
            ->where('currencies.0.projected.0.now', 750000)
            ->where('currencies.0.projected.0.projected', 1150000)
            ->where('view', 'expected')
        );
    }

    /* ---------- projects report ---------- */

    public function test_the_projects_report_summarises_wanted_purchased_and_funded_items_per_project(): void
    {
        $fund = Bucket::factory()->savings()->for($this->user)->rule(FillRule::Fixed, 500000)->create(['name' => 'Hobby fund', 'currency' => 'COP']);
        Bucket::factory()->savings()->for($this->user)->asDefault()->create(['currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($fund)->create(['amount' => 500000, 'currency' => 'COP']);
        $cycling = Project::factory()->for($this->user)->create(['name' => 'Cycling']);

        WishlistItem::factory()->for($this->user)->create(['project_id' => $cycling->id, 'bucket_id' => $fund->id, 'price' => 1000000, 'currency' => 'COP', 'importance' => 5]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $cycling->id, 'price' => 300000, 'currency' => 'COP', 'status' => WishlistStatus::Purchased]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => $cycling->id, 'price' => 50000, 'currency' => 'COP', 'status' => WishlistStatus::Dropped]);
        WishlistItem::factory()->for($this->user)->create(['project_id' => null, 'price' => 200000, 'currency' => 'COP']);
        WishlistItem::factory()->create(['price' => 999, 'currency' => 'COP']);
        $this->plan('income', 500000, '2026-11-05', Certainty::Confirmed);

        $this->get('/reports/projects')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Projects')
            ->has('projects', 2)
            ->where('projects.0.name', 'Cycling')
            ->where('projects.0.counts.wanted', 1)
            ->where('projects.0.counts.purchased', 1)
            ->where('projects.0.counts.dropped', 1)
            ->where('projects.0.totals.0.currency', 'COP')
            ->where('projects.0.totals.0.wanted', 1000000)
            ->where('projects.0.totals.0.purchased', 300000)
            ->where('projects.0.totals.0.fundedPercent', 50)
            ->where('projects.0.totals.0.completion', '2026-11-05')
            ->where('projects.1.name', null)
            ->where('projects.1.totals.0.wanted', 200000)
        );
    }

    /* ---------- dashboard ---------- */

    public function test_the_dashboard_shows_planned_money_for_the_next_thirty_days(): void
    {
        Transaction::factory()->for($this->user)->for(Account::first())->create(['date' => '2026-10-01']);
        $this->plan('income', 500000, '2026-10-20', Certainty::Confirmed);
        $this->plan('income', 300000, '2026-10-25', Certainty::Expected);
        $this->plan('income', 900000, '2026-10-26', Certainty::Speculative);   // not trusted
        $this->plan('expense', 120000, '2026-11-05', Certainty::Confirmed);
        $this->plan('expense', 777777, '2026-12-30', Certainty::Confirmed);    // too far

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('upcoming', 1)
            ->where('upcoming.0.currency', 'COP')
            ->where('upcoming.0.income', 800000)
            ->where('upcoming.0.expense', 120000)
            ->where('upcoming.0.net', 680000)
        );
    }
}
