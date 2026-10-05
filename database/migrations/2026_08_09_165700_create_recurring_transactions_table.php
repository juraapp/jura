<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('frequency');
            $table->date('next_due_date');
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
