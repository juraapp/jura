<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\WishlistService;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IncomeAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $debit;

    private Account $cash;

    private Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);
        $this->debit = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $this->cash = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $this->incomeCategory = Category::factory()->income()->create(['user_id' => $this->user->id]);
    }

    private function income(float $amount, ?Account $account = null, string $date = '2026-10-12'): int
    {
        return app(RecordsTransactions::class)->record($this->user, TransactionType::Income, [
            'account_id' => ($account ?? $this->debit)->id,
            'category_id' => $this->incomeCategory->id,
            'amount' => $amount,
            'date' => $date,
            'description' => 'Pay',
        ]);
    }

    private function funded(Bucket $bucket): float
    {
        return (float) Allocation::where('bucket_id', $bucket->id)->sum('amount');
    }

    private function bucket(string $name, int $priority, FillRule $rule, string|int|null $value = null, array $attributes = []): Bucket
    {
        return Bucket::factory()->savings()->for($this->user)->priority($priority)->rule($rule, $value)
            ->create(array_merge(['name' => $name, 'currency' => 'COP'], $attributes));
    }

    public function test_income_fills_buckets_in_priority_order_and_the_rest_goes_to_the_default_bucket(): void
    {
        $car = $this->bucket('Car', 10, FillRule::Percent, 20);
        $trip = $this->bucket('Trip', 20, FillRule::Fixed, 100);

        $transactionId = $this->income(1000);

        $this->assertSame(200.0, $this->funded($car));
        $this->assertSame(100.0, $this->funded($trip));

        $default = app(BucketLedger::class)->defaultBucket($this->user, 'COP');
        $this->assertNotNull($default);
        $this->assertSame(700.0, $this->funded($default));
        $this->assertSame(3, Allocation::where('source', AllocationSource::Income->value)->where('source_id', $transactionId)->count());
    }

    public function test_nothing_is_allocated_when_no_bucket_has_a_fill_rule(): void
    {
        Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);

        $this->income(1000);

        $this->assertDatabaseCount('allocations', 0);
        $this->assertNull(app(BucketLedger::class)->defaultBucket($this->user, 'COP'));
    }

    public function test_expenses_and_buckets_in_other_currencies_are_left_alone(): void
    {
        $usd = $this->bucket('Dollars', 10, FillRule::Percent, 50, ['currency' => 'USD']);
        $cop = $this->bucket('Pesos', 20, FillRule::Percent, 10);

        app(RecordsTransactions::class)->record($this->user, TransactionType::Expense, [
            'account_id' => $this->debit->id,
            'category_id' => Category::factory()->expense()->create(['user_id' => $this->user->id])->id,
            'amount' => 500, 'date' => '2026-10-12',
        ]);
        $this->assertDatabaseCount('allocations', 0);

        $this->income(1000);

        $this->assertSame(0.0, $this->funded($usd));
        $this->assertSame(100.0, $this->funded($cop));
    }

    public function test_a_bucket_restricted_to_other_accounts_does_not_receive_this_income(): void
    {
        $cashOnly = $this->bucket('Cash stash', 10, FillRule::Percent, 50, ['allowed_account_ids' => [$this->cash->id]]);
        $anywhere = $this->bucket('Anywhere', 20, FillRule::Percent, 10);

        $this->income(1000, $this->debit);
        $this->assertSame(0.0, $this->funded($cashOnly));
        $this->assertSame(100.0, $this->funded($anywhere));

        $this->income(1000, $this->cash);
        $this->assertSame(500.0, $this->funded($cashOnly));
    }

    public function test_earlier_buckets_win_when_the_income_is_too_small(): void
    {
        $first = $this->bucket('First', 1, FillRule::Fixed, 80);
        $second = $this->bucket('Second', 2, FillRule::Fixed, 80);

        $this->income(100);

        $this->assertSame(80.0, $this->funded($first));
        $this->assertSame(20.0, $this->funded($second));
    }

    public function test_fill_to_target_only_tops_up_what_is_missing(): void
    {
        $goal = $this->bucket('Goal', 1, FillRule::FillToTarget, null, ['cap_amount' => 500]);
        Allocation::factory()->for($this->user)->for($goal)->create(['amount' => 300, 'currency' => 'COP']);

        $this->income(1000);

        $this->assertSame(500.0, $this->funded($goal));
    }

    public function test_savings_buckets_stop_filling_at_their_target(): void
    {
        $goal = $this->bucket('Goal', 1, FillRule::Percent, 50, ['cap_amount' => 120]);

        $this->income(1000);
        $this->assertSame(120.0, $this->funded($goal));

        $this->income(1000);
        $this->assertSame(120.0, $this->funded($goal), 'already full');
    }

    public function test_editing_an_income_reallocates_it(): void
    {
        $car = $this->bucket('Car', 10, FillRule::Percent, 10);
        $transactionId = $this->income(1000);
        $this->assertSame(100.0, $this->funded($car));

        $this->patch("/finances/transactions/{$transactionId}", [
            'account_id' => $this->debit->id,
            'category_id' => $this->incomeCategory->id,
            'amount' => 2000,
            'date' => '2026-10-12',
            'type' => 'income',
        ])->assertRedirect();

        $this->assertSame(200.0, $this->funded($car));
        $this->assertSame(1, Allocation::where('bucket_id', $car->id)->count());
    }

    public function test_deleting_an_income_removes_its_allocations(): void
    {
        $car = $this->bucket('Car', 10, FillRule::Percent, 10);
        $transactionId = $this->income(1000);
        Allocation::factory()->for($this->user)->for($car)->create(['amount' => 5, 'currency' => 'COP', 'source' => AllocationSource::Adjustment]);

        $this->delete("/finances/transactions/{$transactionId}")->assertRedirect();

        $this->assertSame(5.0, $this->funded($car), 'only the manual adjustment remains');
        $this->assertDatabaseMissing('allocations', ['source_id' => $transactionId]);
    }

    public function test_incomes_created_through_the_transactions_page_also_fill_buckets(): void
    {
        $car = $this->bucket('Car', 10, FillRule::Percent, 10);

        $this->post('/finances/transactions', [
            'type' => 'income',
            'account_id' => $this->debit->id,
            'category_id' => $this->incomeCategory->id,
            'amount' => 3000,
            'date' => '2026-10-12',
        ])->assertRedirect();

        $this->assertSame(300.0, $this->funded($car));
    }

    public function test_realizing_a_planned_income_with_overrides_allocates_exactly_what_was_chosen(): void
    {
        $car = $this->bucket('Car', 10, FillRule::Percent, 90);
        $trip = $this->bucket('Trip', 20, FillRule::Percent, 5);
        $event = PlannedEvent::factory()->for($this->user)->income()->create([
            'amount' => 1000, 'currency' => 'COP',
            'allocation_overrides' => [['bucket_id' => $trip->id, 'amount' => 300], ['bucket_id' => $car->id, 'amount' => 100]],
        ]);

        $this->post("/planning/cash-flow/{$event->id}/realize", [
            'account_id' => $this->debit->id,
            'category_id' => $this->incomeCategory->id,
        ])->assertRedirect();

        $this->assertSame(300.0, $this->funded($trip));
        $this->assertSame(100.0, $this->funded($car));
        $default = app(BucketLedger::class)->defaultBucket($this->user, 'COP');
        $this->assertSame(600.0, $this->funded($default));
        $this->assertSame(
            $event->fresh()->realized_transaction_id,
            Allocation::where('bucket_id', $trip->id)->firstOrFail()->source_id,
        );
        $this->assertSame(AllocationSource::Planned, Allocation::where('bucket_id', $trip->id)->firstOrFail()->source);
        $this->assertSame(1, Transaction::count());
    }

    public function test_a_planned_event_can_store_allocation_overrides_but_only_for_own_buckets(): void
    {
        $car = $this->bucket('Car', 10, FillRule::Percent, 10);
        $foreign = Bucket::factory()->savings()->create(['currency' => 'COP']);

        $this->post('/planning/cash-flow', [
            'type' => 'income', 'description' => 'Client', 'amount' => 500, 'date' => '2026-11-01', 'certainty' => 'confirmed',
            'allocation_overrides' => [['bucket_id' => $car->id, 'amount' => 200]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals([['bucket_id' => $car->id, 'amount' => 200]], PlannedEvent::firstOrFail()->allocation_overrides);

        $this->post('/planning/cash-flow', [
            'type' => 'income', 'description' => 'Client', 'amount' => 500, 'date' => '2026-11-01', 'certainty' => 'confirmed',
            'allocation_overrides' => [['bucket_id' => $foreign->id, 'amount' => 200]],
        ])->assertSessionHasErrors('allocation_overrides.0.bucket_id');
    }

    public function test_a_fixed_fill_on_a_bucket_with_a_cycle_is_per_cycle_not_per_income(): void
    {
        $leisure = $this->bucket('Leisure', 0, FillRule::Fixed, 150000, [
            'cycle' => 'monthly', 'cap_amount' => 150000, 'kind' => 'spending',
        ]);
        $savings = $this->bucket('Savings', 1, FillRule::Fixed, 300000, ['cycle' => 'monthly']);
        $car = $this->bucket('Purchase', 2, FillRule::PercentOfRemaining, '80.952381', ['cap_amount' => 10000000]);
        $restoration = $this->bucket('Restoration', 3, FillRule::PercentOfRemaining, 100);

        foreach ([2000000, 1000000, 1500000] as $amount) {
            $this->income($amount);
        }

        $this->assertSame(150000.0, $this->funded($leisure));
        $this->assertSame(300000.0, $this->funded($savings));
        $this->assertEqualsWithDelta(3278571.0, $this->funded($car), 2);
        $this->assertEqualsWithDelta(771429.0, $this->funded($restoration), 2);

        $this->income(2000000, null, '2026-11-02');

        $this->assertSame(300000.0, $this->funded($leisure));
        $this->assertSame(600000.0, $this->funded($savings));
    }

    public function test_buying_the_item_of_a_completing_bucket_closes_it_and_a_waiting_bucket_steps_up(): void
    {
        $restoration = $this->bucket('Restoration', 3, FillRule::Fixed, 10);
        $car = $this->bucket('Car', 2, FillRule::Fixed, 500000, [
            'cap_amount' => 1000000, 'completes_on_purchase' => true, 'carry_over' => 'overflow', 'overflow_bucket_id' => $restoration->id,
        ]);
        $savings = $this->bucket('Savings', 1, FillRule::Fixed, 100, [
            'cycle' => 'monthly', 'step_up_value' => 300, 'step_up_after_bucket_id' => $car->id,
        ]);

        app(BucketLedger::class)->credit($car, Money::of(1200000), AllocationSource::Adjustment, null, Carbon::today());
        $expenseCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $item = WishlistItem::factory()->for($this->user)->create(['price' => 1000000, 'currency' => 'COP', 'bucket_id' => $car->id]);

        app(WishlistService::class)->purchase($item, ['account_id' => $this->debit->id, 'category_id' => $expenseCategory->id]);

        $this->assertSame('2026-10-12', $car->fresh()->completed_on->toDateString());
        $this->assertSame(0.0, $this->funded($car));
        $this->assertSame(200000.0, $this->funded($restoration));

        $this->income(10000, null, '2026-10-20');
        $this->assertSame(0.0, $this->funded($car), 'a completed bucket takes no more income');
        $this->assertSame(100.0, $this->funded($savings));
        $this->assertSame(200010.0, $this->funded($restoration));

        $this->income(10000, null, '2026-11-03');
        $this->assertSame(400.0, $this->funded($savings), 'October still paid 100; November steps up to 300');

        app(BucketLedger::class)->reopen($car->fresh());
        $this->assertNull($car->fresh()->completed_on);
    }
}
