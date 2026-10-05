<?php

namespace App\Providers;

use App\Events\TransactionsMutated;
use App\Listeners\CheckBudgetThreshold;
use App\Listeners\CheckLowAccountBalance;
use App\Listeners\InvalidateBalanceCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(TransactionsMutated::class, InvalidateBalanceCache::class);
        Event::listen(TransactionsMutated::class, CheckBudgetThreshold::class);
        Event::listen(TransactionsMutated::class, CheckLowAccountBalance::class);
    }
}
