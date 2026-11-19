<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Services\BalanceCalculator;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Putting more in buckets than is ready to assign asks for confirmation first.
 */
class OverAssignTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bucket $bucket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
        $this->bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Trip']);
        $this->actingAs($this->user);
    }

    private function ready(): string
    {
        return app(MoneyMap::class)->readyToAssign($this->user, 'COP')->toDecimalString();
    }

    public function test_adding_more_than_is_ready_asks_for_confirmation_and_then_over_assigns(): void
    {
        $this->post("/planning/buckets/{$this->bucket->id}/adjust", ['amount' => 1200])->assertSessionHasErrors('over_assign');
        $this->assertSame(0, Allocation::count());

        $this->post("/planning/buckets/{$this->bucket->id}/adjust", ['amount' => 1000])->assertSessionHasNoErrors();
        $this->post("/planning/buckets/{$this->bucket->id}/adjust", ['amount' => 200, 'confirm_over_assign' => true])->assertSessionHasNoErrors();

        $this->assertSame('-200.00', $this->ready());
    }

    public function test_an_opening_balance_only_checks_what_it_adds(): void
    {
        $this->post('/planning/buckets', ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 1500])
            ->assertSessionHasErrors('over_assign');
        $this->assertSame(1, Bucket::count());

        $this->post('/planning/buckets', ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 900])->assertSessionHasNoErrors();
        $car = Bucket::where('name', 'Car')->firstOrFail();

        $this->patch("/planning/buckets/{$car->id}", ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 1000])
            ->assertSessionHasNoErrors();
        $this->patch("/planning/buckets/{$car->id}", ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 1001])
            ->assertSessionHasErrors('over_assign');
        $this->assertSame('0.00', $this->ready());
    }

    public function test_money_can_move_between_a_bucket_and_ready_to_assign(): void
    {
        $this->post("/planning/buckets/{$this->bucket->id}/move", ['from_ready' => true, 'amount' => 1100])->assertSessionHasErrors('over_assign');
        $this->post("/planning/buckets/{$this->bucket->id}/move", ['from_ready' => true, 'amount' => 600])->assertSessionHasNoErrors();
        $this->assertSame('400.00', $this->ready());

        $this->post("/planning/buckets/{$this->bucket->id}/move", ['to_bucket_id' => 'ready', 'amount' => 700])->assertSessionHasErrors('amount');
        $this->post("/planning/buckets/{$this->bucket->id}/move", ['to_bucket_id' => 'ready', 'amount' => 250])->assertSessionHasNoErrors();

        $this->assertSame('650.00', $this->ready());
        $this->assertSame(2, Allocation::where('source', AllocationSource::Adjustment->value)->count());
    }

    public function test_changing_a_funded_buckets_category_asks_when_it_takes_more_than_is_ready(): void
    {
        $groceries = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Groceries']);
        $fun = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Fun']);
        $account = Account::where('user_id', $this->user->id)->sole();
        $spending = Bucket::factory()->for($this->user)->create([
            'currency' => 'COP', 'category_id' => $groceries->id, 'cap_amount' => 500, 'cycle' => 'monthly',
            'tracking_started_on' => $this->user->today()->subDays(5)->toDateString(),
        ]);
        app(BucketLedger::class)->credit($spending, Money::of(300), AllocationSource::Adjustment, null, null);
        foreach ([[$groceries, 100], [$fun, 40]] as [$category, $amount]) {
            Transaction::factory()->for($this->user)->create([
                'account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense', 'amount' => $amount, 'date' => $this->user->today()->toDateString(),
            ]);
        }
        app(BalanceCalculator::class)->forgetAccount($account);
        // 1,000 - 100 - 40 = 860 in the account. Groceries' envelope: 300 - 100 = 200. Ready to assign: 660.
        $this->assertSame('660.00', $this->ready());
        // 650 more is earmarked elsewhere: 10 left to assign.
        app(BucketLedger::class)->credit($this->bucket, Money::of(650), AllocationSource::Adjustment, null, null);
        $this->assertSame('10.00', $this->ready());

        $payload = ['kind' => 'spending', 'category_id' => $fun->id, 'currency' => 'COP', 'cap_amount' => 500, 'cycle' => 'monthly'];

        // Tracking "Fun" instead: its envelope is 300 - 40 = 260, 60 more than 200, but only 10 is ready.
        $this->patch("/planning/buckets/{$spending->id}", $payload)->assertSessionHasErrors('over_assign');
        $this->assertSame($groceries->id, $spending->fresh()->category_id);

        $this->patch("/planning/buckets/{$spending->id}", $payload + ['confirm_over_assign' => true])->assertSessionHasNoErrors();
        $this->assertSame($fun->id, $spending->fresh()->category_id);
        $this->assertSame('-50.00', $this->ready(), '10 - 60');
    }
}
