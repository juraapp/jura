<?php

namespace App\Modules\Planning;

use App\Modules\Ledger\Events\AccountBudgetChanged;
use App\Modules\Ledger\Events\ExpenseRecorded;
use App\Modules\Ledger\Events\ExpenseRemoved;
use App\Modules\Ledger\Events\IncomeRecorded;
use App\Modules\Ledger\Events\IncomeRemoved;
use App\Modules\Ledger\Events\TransactionDeleted;
use App\Modules\Ledger\Events\TransactionPurged;
use App\Modules\Ledger\Events\TransactionRestored;
use App\Modules\Planning\Console\Commands\CloseBucketCycles;
use App\Modules\Planning\Console\Commands\ExtendPlannedEventSeries;
use App\Modules\Planning\Contracts\BucketProgress;
use App\Modules\Planning\Contracts\CashFlowForecast;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Contracts\PlanningOverview;
use App\Modules\Planning\Contracts\UpcomingPlans;
use App\Modules\Planning\Listeners\AllocateIncome;
use App\Modules\Planning\Listeners\ReleaseIncomeAllocations;
use App\Modules\Planning\Listeners\RelinkRestoredRecords;
use App\Modules\Planning\Listeners\ReverseLinkedRecords;
use App\Modules\Planning\Listeners\SyncAccountBudget;
use App\Modules\Planning\Listeners\SyncExpenseSpend;
use App\Modules\Planning\Listeners\UnlinkPurgedRecords;
use App\Modules\Planning\Services\BucketService;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\ExpenseSpending;
use App\Modules\Planning\Services\ForecastInputBuilder;
use App\Modules\Planning\Services\IncomeAllocator;
use App\Modules\Planning\Services\MoneyMapService;
use App\Modules\Planning\Services\PlanningOverviewService;
use App\Modules\Planning\Services\UpcomingPlansService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BucketProgress::class, BucketService::class);
        $this->app->bind(CashFlowForecast::class, CashFlowProjector::class);
        $this->app->bind(PlanningOverview::class, PlanningOverviewService::class);
        $this->app->bind(MoneyMap::class, MoneyMapService::class);
        $this->app->bind(UpcomingPlans::class, UpcomingPlansService::class);
        $this->app->singleton(IncomeAllocator::class);
        $this->app->singleton(ExpenseSpending::class);
        $this->app->scoped(ForecastInputBuilder::class);
    }

    public function boot(): void
    {
        Event::listen(IncomeRecorded::class, AllocateIncome::class);
        Event::listen(IncomeRemoved::class, ReleaseIncomeAllocations::class);
        Event::listen(ExpenseRecorded::class, [SyncExpenseSpend::class, 'handleRecorded']);
        Event::listen(ExpenseRemoved::class, [SyncExpenseSpend::class, 'handleRemoved']);
        Event::listen(TransactionDeleted::class, ReverseLinkedRecords::class);
        Event::listen(TransactionRestored::class, RelinkRestoredRecords::class);
        Event::listen(TransactionPurged::class, UnlinkPurgedRecords::class);
        Event::listen(AccountBudgetChanged::class, SyncAccountBudget::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExtendPlannedEventSeries::class, CloseBucketCycles::class]);
        }
    }
}
