<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting one occurrence used to leave a hidden "skipped" tombstone. The date is
     * now remembered on the series and the row is really deleted. Occurrences that
     * were skipped on purpose cannot be told apart from tombstones, so skipped
     * rows stay visible and the user can reopen or delete them.
     */
    public function up(): void
    {
        Schema::table('planned_event_series', function (Blueprint $table) {
            $table->json('excluded_dates')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('planned_event_series', function (Blueprint $table) {
            $table->dropColumn('excluded_dates');
        });
    }
};
