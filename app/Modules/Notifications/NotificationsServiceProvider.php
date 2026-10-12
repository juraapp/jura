<?php

namespace App\Modules\Notifications;

use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Notifications\Console\Commands\NotifyBehindSavingsGoals;
use App\Modules\Notifications\Console\Commands\NotifyUpcomingRecurringPayments;
use App\Modules\Notifications\Listeners\CheckBudgetThreshold;
use App\Modules\Notifications\Listeners\CheckLowAccountBalance;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(TransactionsMutated::class, CheckBudgetThreshold::class);
        Event::listen(TransactionsMutated::class, CheckLowAccountBalance::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                NotifyBehindSavingsGoals::class,
                NotifyUpcomingRecurringPayments::class,
            ]);
        }
    }
}
