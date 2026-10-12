<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('planned_event_series')->nullOnDelete();
            $table->string('type', 10);
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->date('date');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certainty', 12)->default('expected');
            $table->string('status', 10)->default('planned');
            $table->foreignId('realized_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->json('allocation_overrides')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_events');
    }
};
