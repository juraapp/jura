<?php

use Database\Seeders\CategorySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // user_id null => system (seeded) category, visible to everyone.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('icon', 32)->default('tag');
            $table->string('color', 7)->default('#6b7280');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
        });

        // System categories are reference data, not development data: they're
        // seeded here so they exist in any environment (including production)
        // as soon as `migrate` runs, without depending on someone remembering
        // to run `db:seed`.
        (new CategorySeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
