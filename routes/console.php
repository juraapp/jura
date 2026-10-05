<?php

use App\Jobs\GenerateDueRecurringTransactions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDueRecurringTransactions)->dailyAt('06:00');
Schedule::command('app:notify-upcoming-recurring-payments')->dailyAt('07:00');
Schedule::command('app:notify-behind-savings-goals')->weeklyOn(1, '07:00');
