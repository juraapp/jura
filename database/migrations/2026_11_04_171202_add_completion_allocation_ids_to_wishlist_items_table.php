<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The allocations a purchase's bucket completion created (the leftover released to ready to assign or moved to the
 * overflow bucket), so undoing the purchase can take that leftover back. Purchases made before this column existed
 * have none and keep the old behaviour (the leftover stays where it went).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->json('completion_allocation_ids')->nullable()->after('purchase_allocation_id');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('completion_allocation_ids');
        });
    }
};
