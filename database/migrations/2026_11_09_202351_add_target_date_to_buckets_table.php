<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A savings bucket can have a target date: the day it should hold its target (cap) by. It drives the pace shown on
 * the bucket and the "behind schedule" alert (which replaces the one savings goals had).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->date('target_date')->nullable()->after('cap_amount');
        });
    }

    public function down(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->dropColumn('target_date');
        });
    }
};
