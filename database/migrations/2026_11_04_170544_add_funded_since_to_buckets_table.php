<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a bucket was first funded, stored instead of recomputed from its allocations: removing or redating the
 * earliest allocation must not drop the category spending recorded since then from the envelope. Existing
 * buckets start from their earliest allocation, which is what the live computation used until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->date('funded_since')->nullable()->after('tracking_started_on');
        });

        DB::table('buckets')->update([
            'funded_since' => DB::table('allocations')->selectRaw('MIN(allocations.date)')->whereColumn('allocations.bucket_id', 'buckets.id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->dropColumn('funded_since');
        });
    }
};
