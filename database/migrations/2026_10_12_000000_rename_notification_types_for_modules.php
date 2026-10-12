<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TYPES = [
        'App\\Notifications\\BudgetThresholdReached' => 'App\\Modules\\Notifications\\Notifications\\BudgetThresholdReached',
        'App\\Notifications\\GoalBehindSchedule' => 'App\\Modules\\Notifications\\Notifications\\GoalBehindSchedule',
        'App\\Notifications\\LowAccountBalance' => 'App\\Modules\\Notifications\\Notifications\\LowAccountBalance',
        'App\\Notifications\\UpcomingRecurringPayment' => 'App\\Modules\\Notifications\\Notifications\\UpcomingRecurringPayment',
    ];

    public function up(): void
    {
        foreach (self::TYPES as $old => $new) {
            DB::table('notifications')->where('type', $old)->update(['type' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::TYPES as $old => $new) {
            DB::table('notifications')->where('type', $new)->update(['type' => $old]);
        }
    }
};
