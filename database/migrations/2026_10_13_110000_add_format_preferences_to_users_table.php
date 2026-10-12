<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Display formats. "auto" (or null) follows the language, so nothing changes
     * for anyone until they pick something.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('date_format', 8)->default('auto')->after('timezone');
            $table->string('time_format', 8)->default('auto')->after('date_format');
            $table->string('decimal_separator', 1)->nullable()->after('time_format');
            $table->string('thousands_separator', 4)->nullable()->after('decimal_separator');
            $table->unsignedTinyInteger('fraction_digits')->nullable()->after('thousands_separator');
            $table->string('currency_display', 8)->default('auto')->after('fraction_digits');
            $table->string('currency_position', 16)->default('auto')->after('currency_display');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['date_format', 'time_format', 'decimal_separator', 'thousands_separator', 'fraction_digits', 'currency_display', 'currency_position']);
        });
    }
};
