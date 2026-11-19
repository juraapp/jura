<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A project can have a budget, a target date and a savings bucket of its own. It also gets one currency, so its
 * numbers can be added up: each existing project takes the currency most of its items use (the user's default
 * currency when it has none).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->after('kind');
            $table->decimal('budget_amount', 15, 2)->nullable()->after('currency');
            $table->date('target_date')->nullable()->after('budget_amount');
            $table->foreignId('bucket_id')->nullable()->unique()->after('target_date')->constrained('buckets')->nullOnDelete();
        });

        foreach (DB::table('projects')->get(['id', 'user_id']) as $project) {
            $currency = DB::table('wishlist_items')
                ->where('project_id', $project->id)
                ->select('currency', DB::raw('COUNT(*) as uses'))
                ->groupBy('currency')
                ->orderByDesc('uses')
                ->orderBy('currency')
                ->value('currency')
                ?? DB::table('users')->where('id', $project->user_id)->value('currency_default');

            DB::table('projects')->where('id', $project->id)->update(['currency' => $currency]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bucket_id');
            $table->dropColumn(['currency', 'budget_amount', 'target_date']);
        });
    }
};
