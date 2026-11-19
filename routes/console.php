<?php

use App\Modules\Planning\Jobs\PostDuePlannedEvents;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PostDuePlannedEvents)->hourlyAt(5);
Schedule::command('app:notify-upcoming-planned-payments')->dailyAt('07:00');
Schedule::command('app:notify-behind-savings-buckets')->weeklyOn(1, '07:00');
Schedule::command('planning:extend-series')->dailyAt('05:30');
Schedule::command('planning:close-bucket-cycles')->hourlyAt(30)->withoutOverlapping();
