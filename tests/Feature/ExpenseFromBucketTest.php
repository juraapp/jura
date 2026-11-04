<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * An expense can say which bucket paid for it; the bucket's envelope then spends its full amount.
 */
class ExpenseFromBucketTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $category;

    private Bucket $trip;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 09:00:00');

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
        $this->category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->trip = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Trip']);
        app(BucketLedger::class)->credit($this->trip, Money::of(300), AllocationSource::Adjustment);
        $this->actingAs($this->user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function expense(array $overrides = []): array
    {
        return array_merge([
            'type' => 'expense', 'account_id' => $this->account->id, 'category_id' => $this->category->id,
            'amount' => 500, 'date' => '2026-10-12', 'description' => 'Hotel', 'bucket_id' => $this->trip->id,
        ], $overrides);
    }

    private function envelope(Bucket $bucket): string
    {
        return app(BucketLedger::class)->envelope($bucket->fresh())->toDecimalString();
    }

    private function ready(): string
    {
        return app(MoneyMap::class)->readyToAssign($this->user, 'COP')->toDecimalString();
    }

    public function test_an_expense_paid_from_a_bucket_spends_its_full_amount_and_leaves_ready_to_assign_alone(): void
    {
        $this->assertSame('700.00', $this->ready());

        $this->post('/finances/transactions', $this->expense())->assertSessionHasNoErrors();

        $transaction = Transaction::sole();
        $this->assertSame($this->trip->id, $transaction->bucket_id);
        $this->assertSame('-200.00', $this->envelope($this->trip), 'it may overspend the bucket');
        $spend = Allocation::where('source', AllocationSource::Spend->value)->sole();
        $this->assertSame($transaction->id, $spend->source_id);
        $this->assertSame('700.00', $this->ready());
    }

    public function test_editing_moving_and_removing_the_expense_keeps_the_spend_in_step(): void
    {
        $this->post('/finances/transactions', $this->expense())->assertSessionHasNoErrors();
        $id = Transaction::sole()->id;
        $car = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Car']);

        $this->patch("/finances/transactions/{$id}", $this->expense(['amount' => 100]))->assertSessionHasNoErrors();
        $this->assertSame('200.00', $this->envelope($this->trip));

        $this->patch("/finances/transactions/{$id}", $this->expense(['amount' => 100, 'bucket_id' => $car->id]))->assertSessionHasNoErrors();
        $this->assertSame('300.00', $this->envelope($this->trip));
        $this->assertSame('-100.00', $this->envelope($car));

        $this->delete("/finances/transactions/{$id}")->assertRedirect();
        $this->assertSame('0.00', $this->envelope($car));

        $this->post("/settings/trash/transactions/{$id}/restore")->assertRedirect();
        $this->assertSame('-100.00', $this->envelope($car));

        $this->patch("/finances/transactions/{$id}", $this->expense(['amount' => 100, 'bucket_id' => null]))->assertSessionHasNoErrors();
        $this->assertSame(0, Allocation::where('source', AllocationSource::Spend->value)->count());
    }

    public function test_deleting_the_bucket_forgets_the_link(): void
    {
        $this->post('/finances/transactions', $this->expense())->assertSessionHasNoErrors();

        $this->delete("/planning/buckets/{$this->trip->id}")->assertRedirect();

        $this->assertNull(Transaction::sole()->bucket_id);
    }

    public function test_an_expense_paid_from_a_bucket_does_not_also_shrink_its_categorys_envelope(): void
    {
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->category->id, 'tracking_started_on' => '2026-10-01']);
        app(BucketLedger::class)->credit($groceries, Money::of(200), AllocationSource::Adjustment, null, Carbon::parse('2026-10-01'));

        $this->post('/finances/transactions', $this->expense(['amount' => 50]))->assertSessionHasNoErrors();
        $this->post('/finances/transactions', $this->expense(['amount' => 30, 'bucket_id' => null]))->assertSessionHasNoErrors();

        $this->assertSame('170.00', $this->envelope($groceries), 'only the expense not paid from a bucket counts');
        $this->assertSame('250.00', $this->envelope($this->trip));
    }

    public function test_moving_an_expense_to_an_off_budget_account_unlinks_its_bucket(): void
    {
        $this->post('/finances/transactions', $this->expense())->assertSessionHasNoErrors();
        $deposit = Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP']);

        $this->post('/finances/transactions/bulk-update', ['ids' => [Transaction::sole()->id], 'account_id' => $deposit->id])->assertSessionHasNoErrors();

        $this->assertNull(Transaction::sole()->bucket_id);
        $this->assertSame('300.00', $this->envelope($this->trip));
    }

    public function test_only_an_own_spendable_bucket_in_the_accounts_currency_on_an_on_budget_expense_is_accepted(): void
    {
        $foreign = Bucket::factory()->savings()->create(['currency' => 'COP']);
        $dollars = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'USD']);
        $categoryBucket = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->category->id]);
        $deposit = Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP']);
        $incomeCategory = Category::factory()->income()->create(['user_id' => $this->user->id]);

        foreach ([$foreign->id, $dollars->id, $categoryBucket->id] as $bucketId) {
            $this->post('/finances/transactions', $this->expense(['bucket_id' => $bucketId]))->assertSessionHasErrors('bucket_id');
        }
        $this->post('/finances/transactions', $this->expense(['account_id' => $deposit->id]))->assertSessionHasErrors('bucket_id');
        $this->post('/finances/transactions', $this->expense(['type' => 'income', 'category_id' => $incomeCategory->id]))->assertSessionHasErrors('bucket_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_the_modal_gets_the_buckets_and_the_expenses_bucket(): void
    {
        $this->post('/finances/transactions', $this->expense())->assertSessionHasNoErrors();

        $this->getJson('/planning/buckets/spend-options')->assertOk()
            ->assertJsonPath('buckets.0.id', $this->trip->id)
            ->assertJsonPath('buckets.0.categoryId', null)
            ->assertJsonPath('buckets.0.available', -200);
        $this->getJson('/finances/transactions/'.Transaction::sole()->id.'/edit-data')->assertJsonPath('bucket_id', $this->trip->id);
    }

    public function test_a_completed_bucket_takes_no_new_expense_but_an_expense_already_paid_from_it_keeps_the_link(): void
    {
        $this->post('/finances/transactions', $this->expense(['amount' => 100]))->assertSessionHasNoErrors();
        $id = Transaction::sole()->id;
        $this->trip->update(['completed_on' => '2026-10-12']);

        $this->post('/finances/transactions', $this->expense())->assertSessionHasErrors('bucket_id');

        $this->patch("/finances/transactions/{$id}", $this->expense(['amount' => 120]))->assertSessionHasNoErrors();
        $this->assertSame($this->trip->id, Transaction::find($id)->bucket_id);
    }

    public function test_spend_options_can_include_the_bucket_an_expense_is_already_linked_to(): void
    {
        $this->trip->update(['completed_on' => '2026-10-12']);

        $this->getJson('/planning/buckets/spend-options')->assertOk()->assertJsonCount(0, 'buckets');
        $this->getJson('/planning/buckets/spend-options?include='.$this->trip->id)->assertOk()
            ->assertJsonPath('buckets.0.id', $this->trip->id)
            ->assertJsonPath('buckets.0.current', true);

        $foreign = Bucket::factory()->savings()->create(['currency' => 'COP', 'completed_on' => '2026-10-12']);
        $this->getJson('/planning/buckets/spend-options?include='.$foreign->id)->assertOk()->assertJsonCount(0, 'buckets');
    }
}
