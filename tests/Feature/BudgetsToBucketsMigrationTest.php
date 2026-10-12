<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Category;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetsToBucketsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_budgets_collapse_into_one_spending_bucket_per_category_currency_and_cycle(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->nullable();
            $table->string('period_type');
            $table->date('period_start');
            $table->timestamps();
        });

        $user = User::factory()->create();
        $food = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Food']);
        $fun = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Fun']);

        $row = fn (Category $category, string $currency, string $type, string $start, float $amount) => DB::table('budgets')->insert([
            'user_id' => $user->id, 'category_id' => $category->id, 'currency' => $currency, 'amount' => $amount,
            'period_type' => $type, 'period_start' => $start, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row($food, 'COP', 'monthly', '2026-08-01', 100);
        $row($food, 'COP', 'monthly', '2026-10-01', 300); // latest wins
        $row($food, 'COP', 'monthly', '2026-09-01', 200);
        $row($food, 'COP', 'yearly', '2026-01-01', 5000);
        $row($food, 'USD', 'monthly', '2026-10-01', 40);
        $row($fun, 'COP', 'monthly', '2026-10-01', 60);

        (require database_path('migrations/2026_10_12_110100_migrate_budgets_to_buckets.php'))->up();

        $this->assertFalse(Schema::hasTable('budgets'));
        $this->assertSame(4, Bucket::withoutGlobalScopes()->count());

        $monthlyFood = Bucket::withoutGlobalScopes()->where('category_id', $food->id)->where('currency', 'COP')->where('cycle', 'monthly')->firstOrFail();
        $this->assertSame('300.00', $monthlyFood->cap_amount->toDecimalString());
        $this->assertSame('Food', $monthlyFood->name);
        $this->assertSame(BucketKind::Spending, $monthlyFood->kind);
        $this->assertTrue($monthlyFood->tracksLimit());
    }
}
