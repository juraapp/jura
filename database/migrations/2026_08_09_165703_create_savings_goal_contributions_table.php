<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->foreignId('linked_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goal_contributions');
    }
};
