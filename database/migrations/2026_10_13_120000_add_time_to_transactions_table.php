<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional wall-clock time in the user's time zone. Rows without one keep sorting by date and id.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->time('time')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('time');
        });
    }
};
