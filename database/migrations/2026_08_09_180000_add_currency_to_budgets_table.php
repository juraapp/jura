<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->char('currency', 3)->default('COP')->after('category_id');
        });

        // The old unique index also backs the `user_id` foreign key, so the
        // new one must exist before the old one is dropped or MariaDB
        // refuses the drop ("needed in a foreign key constraint").
        Schema::table('budgets', function (Blueprint $table) {
            $table->unique(['user_id', 'category_id', 'currency', 'period_type', 'period_start'], 'budgets_user_category_currency_period_unique');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'category_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->unique(['user_id', 'category_id', 'period_type', 'period_start']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique('budgets_user_category_currency_period_unique');
            $table->dropColumn('currency');
        });
    }
};
