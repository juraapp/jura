<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional amount a savings bucket aims to receive in each of its cycles
     * (in addition to its overall target).
     */
    public function up(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->decimal('cycle_goal', 15, 2)->nullable()->after('cycle_anchor');
        });
    }

    public function down(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->dropColumn('cycle_goal');
        });
    }
};
