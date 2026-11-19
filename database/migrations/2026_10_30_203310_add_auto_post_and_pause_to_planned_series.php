<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A repeating plan can be paused, can record itself automatically on its dates, and keeps notes and an
     * income's bucket split for its occurrences. Each occurrence carries its own auto_post flag (so a one-off
     * plan can auto-post too) and the reason its last automatic post was blocked.
     */
    public function up(): void
    {
        Schema::table('planned_event_series', function (Blueprint $table) {
            $table->boolean('auto_post')->default(false)->after('ends_on');
            $table->boolean('is_active')->default(true)->after('auto_post');
            $table->text('notes')->nullable()->after('is_active');
            $table->json('allocation_overrides')->nullable()->after('notes');

            $table->index(['user_id', 'is_active']);
        });

        Schema::table('planned_events', function (Blueprint $table) {
            $table->boolean('auto_post')->default(false)->after('status');
            $table->string('post_error', 40)->nullable()->after('auto_post');

            $table->index(['auto_post', 'status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('planned_events', function (Blueprint $table) {
            $table->dropIndex(['auto_post', 'status', 'date']);
            $table->dropColumn(['auto_post', 'post_error']);
        });

        Schema::table('planned_event_series', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropColumn(['auto_post', 'is_active', 'notes', 'allocation_overrides']);
        });
    }
};
