<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Budget forecasts were flat projected expenses; planned events replace them.
     * Anticipated forecasts become "expected", the rest "speculative".
     */
    public function up(): void
    {
        if (! Schema::hasTable('budget_forecasts')) {
            return;
        }

        DB::table('budget_forecasts')
            ->leftJoin('budgets', 'budgets.id', '=', 'budget_forecasts.budget_id')
            ->orderBy('budget_forecasts.id')
            ->select('budget_forecasts.*', 'budgets.category_id as budget_category_id')
            ->chunk(200, function ($rows) {
                DB::table('planned_events')->insert($rows->map(fn ($row) => [
                    'user_id' => $row->user_id,
                    'type' => 'expense',
                    'description' => $row->description,
                    'amount' => $row->amount,
                    'currency' => $row->currency,
                    'date' => $row->date,
                    'category_id' => $row->category_id ?? $row->budget_category_id,
                    'certainty' => $row->is_anticipated ? 'expected' : 'speculative',
                    'status' => 'planned',
                    'notes' => $row->notes,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ])->all());
            });

        Schema::drop('budget_forecasts');
    }

    public function down(): void
    {
        // The flat forecast table is not recreated; planned events supersede it.
    }
};
