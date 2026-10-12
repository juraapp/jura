<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->date('completed_on')->nullable()->after('cycle_closed_through');
            $table->boolean('completes_on_purchase')->default(false)->after('completed_on');
            $table->decimal('step_up_value', 15, 4)->nullable()->after('fill_value');
            $table->foreignId('step_up_after_bucket_id')->nullable()->after('step_up_value')->constrained('buckets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('step_up_after_bucket_id');
            $table->dropColumn(['completed_on', 'completes_on_purchase', 'step_up_value']);
        });
    }
};
