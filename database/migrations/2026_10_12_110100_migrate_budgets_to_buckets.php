<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Budgets were one row per category and period. Buckets are persistent, so
     * each (user, category, currency, period type) collapses into one spending
     * bucket using the most recent period's amount as its cycle limit.
     */
    public function up(): void
    {
        if (! Schema::hasTable('budgets')) {
            return;
        }

        $groups = DB::table('budgets')
            ->select('user_id', 'category_id', 'currency', 'period_type', DB::raw('MAX(period_start) as latest'))
            ->groupBy('user_id', 'category_id', 'currency', 'period_type')
            ->get();

        foreach ($groups as $group) {
            $latest = DB::table('budgets')
                ->where('user_id', $group->user_id)
                ->where('category_id', $group->category_id)
                ->where('currency', $group->currency)
                ->where('period_type', $group->period_type)
                ->where('period_start', $group->latest)
                ->first();

            DB::table('buckets')->insert([
                'user_id' => $group->user_id,
                'name' => DB::table('categories')->where('id', $group->category_id)->value('name') ?? 'Budget',
                'kind' => 'spending',
                'currency' => $group->currency,
                'priority' => 100,
                'category_id' => $group->category_id,
                'cycle' => $group->period_type,
                'cap_amount' => $latest->amount,
                'carry_over' => 'carry',
                'is_default' => false,
                'tracking_started_on' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::drop('budgets');
    }

    public function down(): void
    {
        // Per-period budget rows are not recreated; buckets supersede them.
    }
};
