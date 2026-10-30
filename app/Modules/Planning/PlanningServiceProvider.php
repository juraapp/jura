<?php

namespace App\Modules\Planning;

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
use App\Modules\Planning\Listeners\AllocateIncome;
use App\Modules\Planning\Listeners\ReleaseIncomeAllocations;
use App\Modules\Planning\Listeners\RelinkRestoredRecords;
use App\Modules\Planning\Listeners\ReverseLinkedRecords;
use App\Modules\Planning\Listeners\UnlinkPurgedRecords;
use App\Modules\Planning\Services\BucketService;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\IncomeAllocator;
use App\Modules\Planning\Services\MoneyMapService;
use App\Modules\Planning\Services\PlanningOverviewService;
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
        $this->app->singleton(IncomeAllocator::class);
    }

    public function boot(): void
    {
        Event::listen(IncomeRecorded::class, AllocateIncome::class);
        Event::listen(IncomeRemoved::class, ReleaseIncomeAllocations::class);
        Event::listen(TransactionDeleted::class, ReverseLinkedRecords::class);
        Event::listen(TransactionRestored::class, RelinkRestoredRecords::class);
        Event::listen(TransactionPurged::class, UnlinkPurgedRecords::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExtendPlannedEventSeries::class, CloseBucketCycles::class]);
        }
    }
}
