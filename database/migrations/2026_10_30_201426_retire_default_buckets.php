<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The default ("Unallocated") bucket was a stored copy of ready to assign, which is now computed. Each default
 * bucket is removed, and everything that pointed at it is redirected to ready to assign. The data changes cannot
 * be undone: down() only brings the column back.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $defaults = DB::table('buckets')->where('is_default', true)->pluck('id')->all();

            if ($defaults !== []) {
                DB::table('buckets')->whereIn('overflow_bucket_id', $defaults)
                    ->update(['overflow_bucket_id' => null, 'carry_over' => 'release']);
                DB::table('buckets')->whereIn('step_up_after_bucket_id', $defaults)
                    ->update(['step_up_after_bucket_id' => null, 'step_up_value' => null]);

                DB::table('wishlist_items')->whereIn('bucket_id', $defaults)->update(['bucket_id' => null]);
                DB::table('wishlist_items')
                    ->whereIn('purchase_allocation_id', DB::table('allocations')->whereIn('bucket_id', $defaults)->select('id'))
                    ->update(['purchase_allocation_id' => null]);

                $this->dropOverrides($defaults);

                // Moves from or to the default bucket were moves from or to ready to assign.
                DB::table('allocations')->whereNotIn('bucket_id', $defaults)->where('source', 'transfer')->whereIn('source_id', $defaults)
                    ->update(['source' => 'adjustment', 'source_id' => null]);
                DB::table('allocations')->whereNotIn('bucket_id', $defaults)->where('source', 'carry')->whereIn('source_id', $defaults)
                    ->update(['source' => 'ready', 'source_id' => null]);

                // Allocations and cycle history go with the bucket (cascade).
                DB::table('buckets')->whereIn('id', $defaults)->delete();
            }

            DB::table('buckets')->where('carry_over', 'overflow')->whereNull('overflow_bucket_id')->update(['carry_over' => 'release']);
        });

        Schema::table('buckets', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('allowed_account_ids');
        });
    }

    /**
     * Removes the default buckets from planned incomes' explicit splits (that money now stays ready to assign).
     *
     * @param  list<int>  $defaults
     */
    private function dropOverrides(array $defaults): void
    {
        DB::table('planned_events')->whereNotNull('allocation_overrides')->orderBy('id')->each(function (object $event) use ($defaults) {
            $rows = json_decode($event->allocation_overrides, true);
            if (! is_array($rows)) {
                return;
            }

            $kept = array_values(array_filter($rows, fn ($row) => ! in_array((int) ($row['bucket_id'] ?? 0), $defaults, true)));

            if (count($kept) !== count($rows)) {
                DB::table('planned_events')->where('id', $event->id)->update(['allocation_overrides' => $kept === [] ? null : json_encode($kept)]);
            }
        });
    }
};
