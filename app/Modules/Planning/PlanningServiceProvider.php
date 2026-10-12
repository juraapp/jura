<?php

namespace App\Modules\Planning;

use App\Modules\Planning\Console\Commands\ExtendPlannedEventSeries;
use App\Modules\Planning\Contracts\BucketProgress;
use App\Modules\Planning\Services\BucketService;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BucketProgress::class, BucketService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ExtendPlannedEventSeries::class]);
        }
    }
}
