<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A repeating plan remembers the last date it created an occurrence for, instead of reading the latest
     * stored occurrence: moving that occurrence to another date ("only this one") no longer makes the next
     * top-up create its old date again, or skip the dates in between. Backfilled with each series' latest
     * stored occurrence date, which is what the top-up used until now.
     */
    public function up(): void
    {
        Schema::table('planned_event_series', function (Blueprint $table) {
            $table->date('materialized_until')->nullable()->after('excluded_dates');
        });

        DB::table('planned_event_series')->update([
            'materialized_until' => DB::raw('(select max(planned_events.date) from planned_events where planned_events.series_id = planned_event_series.id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('planned_event_series', function (Blueprint $table) {
            $table->dropColumn('materialized_until');
        });
    }
};
