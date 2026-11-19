<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plan can be a transfer between two of the user's accounts: type transfer_out (12 characters), from
     * account_id to counter_account_id. Deleting the destination account only forgets it.
     */
    public function up(): void
    {
        foreach (['planned_event_series', 'planned_events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('type', 12)->change();
                $table->foreignId('counter_account_id')->nullable()->after('account_id')->constrained('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['planned_event_series', 'planned_events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('counter_account_id');
                $table->string('type', 10)->change();
            });
        }
    }
};
