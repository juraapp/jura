<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            // Null only when type is transfer_out/transfer_in.
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_method')->nullable();
            $table->boolean('is_recurring_generated')->default(false);
            // FK added in add_foreign_key_recurring_transaction_id_to_transactions_table
            // once the recurring_transactions table exists.
            $table->unsignedBigInteger('recurring_transaction_id')->nullable();
            $table->uuid('transfer_group_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'account_id', 'date']);
            $table->index(['user_id', 'category_id', 'date']);
            $table->index(['user_id', 'type']);
            $table->index('transfer_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
