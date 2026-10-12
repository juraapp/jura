<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('yield_rate', 7, 4)->nullable()->after('initial_balance');
            $table->string('yield_compounding', 10)->default('daily')->after('yield_rate');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['yield_rate', 'yield_compounding']);
        });
    }
};
