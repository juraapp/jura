<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bucket's cycle is "every N units from an anchor date". Existing buckets keep
     * behaving exactly as before (every 1 month/year, calendar aligned, no anchor).
     * Each finished cycle is stored as a snapshot so history survives later changes.
     */
    public function up(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->unsignedSmallInteger('cycle_every')->default(1)->after('cycle');
            $table->date('cycle_anchor')->nullable()->after('cycle_every');
        });

        Schema::create('bucket_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bucket_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 8)->default('open');
            $table->decimal('opening', 15, 2)->default(0);
            $table->decimal('filled', 15, 2)->default(0);
            $table->decimal('moved_in', 15, 2)->default(0);
            $table->decimal('moved_out', 15, 2)->default(0);
            $table->decimal('spent', 15, 2)->default(0);
            $table->decimal('released', 15, 2)->default(0);
            $table->decimal('closing', 15, 2)->default(0);
            $table->decimal('cap', 15, 2)->nullable();
            $table->decimal('cap_override', 15, 2)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['bucket_id', 'starts_on']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bucket_cycles');

        Schema::table('buckets', function (Blueprint $table) {
            $table->dropColumn(['cycle_every', 'cycle_anchor']);
        });
    }
};
