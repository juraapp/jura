<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 10);
            $table->string('currency', 3);
            $table->unsignedInteger('priority')->default(100);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cycle', 10)->nullable();
            $table->decimal('cap_amount', 15, 2)->nullable();
            $table->string('fill_rule', 16)->nullable();
            $table->decimal('fill_value', 15, 4)->nullable();
            $table->string('carry_over', 10)->default('carry');
            $table->foreignId('overflow_bucket_id')->nullable()->constrained('buckets')->nullOnDelete();
            $table->json('allowed_account_ids')->nullable();
            $table->boolean('is_default')->default(false);
            $table->date('tracking_started_on');
            $table->date('cycle_closed_through')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'currency', 'priority']);
            $table->unique(['user_id', 'category_id', 'currency', 'cycle']);
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bucket_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->date('date');
            $table->string('source', 12);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['bucket_id', 'date']);
            $table->index(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('buckets');
    }
};
