<?php

namespace App\Modules\Planning;

use App\Modules\Ledger\Events\IncomeRecorded;
use App\Modules\Ledger\Events\IncomeRemoved;
use App\Modules\Planning\Console\Commands\CloseBucketCycles;
use App\Modules\Planning\Console\Commands\ExtendPlannedEventSeries;
use App\Modules\Planning\Contracts\BucketProgress;
use App\Modules\Planning\Listeners\AllocateIncome;
use App\Modules\Planning\Listeners\ReleaseIncomeAllocations;
use App\Modules\Planning\Services\BucketService;
use App\Modules\Planning\Services\IncomeAllocator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BucketProgress::class, BucketService::class);
        $this->app->singleton(IncomeAllocator::class);
    }

    public function boot(): void
    {
        Event::listen(IncomeRecorded::class, AllocateIncome::class);
        Event::listen(IncomeRemoved::class, ReleaseIncomeAllocations::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExtendPlannedEventSeries::class, CloseBucketCycles::class]);
        }
    }
}
