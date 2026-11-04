<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ready to assign = money in on-budget accounts − money in funded bucket envelopes.
 */
class ReadyToAssignTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
    }

    private function fund(Bucket $bucket, int $amount): void
    {
        app(BucketLedger::class)->credit($bucket, Money::of($amount), AllocationSource::Adjustment);
    }

    public function test_funded_buckets_hold_money_and_the_rest_is_ready_to_assign(): void
    {
        $this->fund(Bucket::factory()->savings()->for($this->user)->create(), 300);
        Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP', 'initial_balance' => 5000]);

        $row = app(MoneyMap::class)->summary($this->user)['COP'];

        $this->assertSame('1000.00', $row['inAccounts']->toDecimalString());
        $this->assertSame('300.00', $row['inBuckets']->toDecimalString());
        $this->assertSame('700.00', app(MoneyMap::class)->readyToAssign($this->user, 'COP')->toDecimalString());
    }

    public function test_a_limit_only_spending_bucket_holds_no_money(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        Bucket::factory()->for($this->user)->create(['category_id' => $category->id]);
        Transaction::factory()->for($this->user)->for($account)->create(['category_id' => $category->id, 'amount' => 100, 'date' => today()->toDateString()]);

        $this->assertSame('900.00', app(MoneyMap::class)->readyToAssign($this->user, 'COP')->toDecimalString());
    }

    public function test_the_money_map_is_a_deferred_shared_prop(): void
    {
        $this->fund(Bucket::factory()->savings()->for($this->user)->create(), 1200);

        $probe = $this->actingAs($this->user)->getJson('/dashboard', ['X-Inertia' => 'true']);
        $version = $probe->headers->get('X-Inertia-Version');
        $page = $this->actingAs($this->user)->getJson('/dashboard', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version]);
        $this->assertContains('moneyMap', $page->json('deferredProps.topbar'));

        $partial = $this->actingAs($this->user)->getJson('/dashboard', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Data' => 'moneyMap',
            'X-Inertia-Partial-Component' => 'Dashboard',
        ]);

        $partial->assertOk();
        $this->assertEquals([[
            'currency' => 'COP', 'inAccounts' => 1000.0, 'inBuckets' => 1200.0, 'readyToAssign' => -200.0, 'overAssigned' => true,
        ]], $partial->json('props.moneyMap'));
    }

    public function test_the_money_map_takes_a_constant_number_of_queries(): void
    {
        $account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 100]);
        Account::factory()->for($this->user)->count(4)->create(['currency' => 'COP', 'initial_balance' => 100]);
        foreach (range(1, 10) as $index) {
            $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
            $this->fund(Bucket::factory()->for($this->user)->create(['category_id' => $category->id, 'tracking_started_on' => today()]), 10);
        }
        Transaction::factory()->for($this->user)->for($account)->create([
            'type' => 'expense', 'category_id' => Bucket::first()->category_id, 'amount' => 4, 'date' => today()->toDateString(),
        ]);
        Cache::flush();

        DB::enableQueryLog();
        $row = app(MoneyMap::class)->summary($this->user->fresh())['COP'];
        $queries = count(DB::getQueryLog());

        // 1000 + 5 × 100 - 4 = 1496 in accounts; 10 × 10 - 4 = 96 in buckets; 1400 ready.
        $this->assertLessThanOrEqual(8, $queries);
        $this->assertSame(['1496.00', '96.00', '1400.00'], [
            $row['inAccounts']->toDecimalString(), $row['inBuckets']->toDecimalString(), $row['readyToAssign']->toDecimalString(),
        ]);
    }
}
