<?php

namespace App\Modules\Notifications;

use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Notifications\Console\Commands\NotifyBehindSavingsBuckets;
use App\Modules\Notifications\Console\Commands\NotifyUpcomingPlannedPayments;
use App\Modules\Notifications\Listeners\CheckBudgetThreshold;
use App\Modules\Notifications\Listeners\CheckLowAccountBalance;
use App\Modules\Notifications\Listeners\NotifyPlannedPostingFailed;
use App\Modules\Planning\Events\PlannedPostingFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(TransactionsMutated::class, CheckBudgetThreshold::class);
        Event::listen(TransactionsMutated::class, CheckLowAccountBalance::class);
        Event::listen(PlannedPostingFailed::class, NotifyPlannedPostingFailed::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                NotifyBehindSavingsBuckets::class,
                NotifyUpcomingPlannedPayments::class,
            ]);
        }
    }
}
