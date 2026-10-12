<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bucket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->string('currency', 3);
            $table->unsignedTinyInteger('importance')->default(3);
            $table->date('desired_by')->nullable();
            $table->json('allowed_account_ids')->nullable();
            $table->string('status', 10)->default('wanted');
            $table->date('purchased_on')->nullable();
            $table->foreignId('purchased_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('purchase_allocation_id')->nullable()->constrained('allocations')->nullOnDelete();
            $table->string('url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
