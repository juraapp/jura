<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('currency', 3);
            $table->string('forecast_view', 12)->default('conservative');
            $table->unsignedTinyInteger('horizon_months')->default(12);
            $table->timestamps();
        });

        Schema::create('scenario_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wishlist_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->date('not_before')->nullable();
            $table->timestamps();

            $table->index(['scenario_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_lines');
        Schema::dropIfExists('scenarios');
    }
};
