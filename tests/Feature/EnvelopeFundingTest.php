<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * When a category bucket's envelope starts counting the category's spending, and what assigning money to it
 * really takes from ready to assign. Every expected figure is worked out by hand in the comments.
 */
class EnvelopeFundingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $bank;

    private Category $salary;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 09:00:00');

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->bank = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $this->salary = Category::factory()->income()->create(['user_id' => $this->user->id]);
        $this->food = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    private function ready(): string
    {
        return app(MoneyMap::class)->readyToAssign($this->user->fresh(), 'COP')->toDecimalString();
    }

    private function envelope(Bucket $bucket): string
    {
        return app(BucketLedger::class)->envelope($bucket->fresh())->toDecimalString();
    }

    private function groceries(string $trackingFrom = '2026-10-01', array $attributes = []): Bucket
    {
        return Bucket::factory()->for($this->user)->create(array_merge([
            'name' => 'Groceries', 'currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => $trackingFrom,
        ], $attributes));
    }

    private function record(string $type, int $amount, string $date): int
    {
        $this->post('/finances/transactions', [
            'type' => $type, 'account_id' => $this->bank->id, 'category_id' => $type === 'income' ? $this->salary->id : $this->food->id,
            'amount' => $amount, 'date' => $date, 'description' => $type,
        ])->assertSessionHasNoErrors();

        return Transaction::latest('id')->value('id');
    }

    private function fund(Bucket $bucket, int $amount, string $date): void
    {
        app(BucketLedger::class)->credit($bucket, Money::of($amount), AllocationSource::Adjustment, null, Carbon::parse($date));
    }

    public function test_removing_the_first_income_keeps_the_spending_since_it_in_the_envelope(): void
    {
        $groceries = $this->groceries('2026-09-01', ['fill_rule' => FillRule::Percent, 'fill_value' => 100]);
        $first = $this->record('income', 100, '2026-09-01');
        $this->record('expense', 80, '2026-09-05');
        $this->record('income', 50, '2026-10-01');
        // 100 + 50 in, 80 spent: envelope 70; bank 70; ready 0.
        $this->assertSame('70.00', $this->envelope($groceries));
        $this->assertSame('0.00', $this->ready());

        $this->delete("/finances/transactions/{$first}")->assertRedirect();

        // 50 in, 80 spent = -30, covered back to 0 from ready (by at most the 100 released); bank 50 - 80 = -30.
        $this->assertSame('2026-09-01', $groceries->fresh()->funded_since->toDateString());
        $this->assertSame('0.00', $this->envelope($groceries));
        $this->assertSame('-30.00', $this->ready());

        $this->post("/settings/trash/transactions/{$first}/restore")->assertRedirect();

        // The cover is taken back and the 100 goes in again: 150 - 80 = 70; bank 70; ready 0.
        $this->assertSame('70.00', $this->envelope($groceries));
        $this->assertSame('0.00', $this->ready());
    }

    public function test_a_backdated_allocation_moves_the_funded_date_earlier_but_never_later(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries('2026-09-01');
        $this->fund($groceries, 100, '2026-10-01');
        $this->record('expense', 30, '2026-09-15');
        // Funded on 10-01: the 09-15 expense comes out of ready. Bank 970, envelope 100, ready 870.
        $this->assertSame('100.00', $this->envelope($groceries));
        $this->assertSame('870.00', $this->ready());

        $this->fund($groceries, 20, '2026-09-10');
        // Funded since 09-10 now: 120 - 30 = 90; ready 970 - 90 = 880.
        $this->assertSame('2026-09-10', $groceries->fresh()->funded_since->toDateString());
        $this->assertSame('90.00', $this->envelope($groceries));
        $this->assertSame('880.00', $this->ready());

        Allocation::where('bucket_id', $groceries->id)->whereDate('date', '2026-09-10')->sole()->delete();
        // The date stays 09-10: 100 - 30 = 70; ready 970 - 70 = 900.
        $this->assertSame('2026-09-10', $groceries->fresh()->funded_since->toDateString());
        $this->assertSame('70.00', $this->envelope($groceries));
        $this->assertSame('900.00', $this->ready());
    }

    public function test_removing_the_only_allocation_of_an_unspent_bucket_makes_it_a_limit_again(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries();
        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 200]))->assertSessionHasNoErrors();
        $this->assertSame('800.00', $this->ready());

        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 0]))->assertSessionHasNoErrors();
        $this->record('expense', 40, '2026-10-12');

        // Nothing was ever spent from it, so it is not an envelope any more: the 40 comes out of ready (1000 - 40).
        $this->assertNull($groceries->fresh()->funded_since);
        $this->assertSame('960.00', $this->ready());
    }

    public function test_removing_the_only_allocation_after_spending_leaves_the_envelope_overspent(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries();
        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 100]))->assertSessionHasNoErrors();
        $this->record('expense', 30, '2026-10-12');
        // Bank 970, envelope 70, ready 900.
        $this->assertSame('900.00', $this->ready());

        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 0]))->assertSessionHasNoErrors();

        // The 30 was spent from the envelope: it stays there as -30 (overspent); ready 970 - (-30) = 1000.
        $this->assertSame('2026-10-12', $groceries->fresh()->funded_since->toDateString());
        $this->assertSame('-30.00', $this->envelope($groceries));
        $this->assertSame('1000.00', $this->ready());
    }

    public function test_an_opening_on_a_bucket_whose_category_was_already_spent_takes_exactly_that_amount(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries();
        $this->record('expense', 300, '2026-10-05');
        $this->assertSame('700.00', $this->ready());

        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 400]))->assertSessionHasNoErrors();

        // Dated today, so the 10-05 expense stays out of it: envelope 400, ready 700 - 400 = 300.
        $this->assertSame('2026-10-12', Allocation::where('bucket_id', $groceries->id)->sole()->date->toDateString());
        $this->assertSame('400.00', $this->envelope($groceries));
        $this->assertSame('300.00', $this->ready());

        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 800]))
            ->assertSessionHasErrors(['over_assign' => __('buckets.over_assign', ['amount' => $this->user->formatter()->money(Money::of(100), 'COP')])]);
        $this->assertSame('300.00', $this->ready());
    }

    public function test_the_guard_counts_spending_a_first_opening_brings_in(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries();
        $this->record('expense', 300, '2026-10-12');
        $this->assertSame('700.00', $this->ready());

        // 900 in, but today's 300 then comes out of it: ready only moves by 600 of the 700 there is.
        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 900]))->assertSessionHasNoErrors();

        $this->assertSame('600.00', $this->envelope($groceries));
        $this->assertSame('100.00', $this->ready());
    }

    public function test_creating_a_bucket_for_a_tracked_category_guards_only_the_change_to_its_opening(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries('2026-10-12');
        $this->patch("/planning/buckets/{$groceries->id}", $this->spendingPayload(['opening_balance' => 500]))->assertSessionHasNoErrors();
        $this->assertSame('500.00', $this->ready());

        // The same category, currency and cycle updates that bucket: 700 - 500 = 200 more, and 500 is ready.
        $this->post('/planning/buckets', $this->spendingPayload(['opening_balance' => 700]))->assertSessionHasNoErrors();

        $this->assertSame(1, Bucket::count());
        $this->assertSame('700.00', $this->envelope($groceries));
        $this->assertSame('300.00', $this->ready());
    }

    private function overAssignError(int $amount): array
    {
        return ['over_assign' => __('buckets.over_assign', ['amount' => $this->user->formatter()->money(Money::of($amount), 'COP')])];
    }

    private function savings(string $name, int $funded): Bucket
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['name' => $name, 'currency' => 'COP']);
        $this->fund($bucket, $funded, '2026-10-12');

        return $bucket;
    }

    public function test_reset_brings_an_overspent_envelope_to_zero_from_ready_to_assign(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries();
        $this->fund($groceries, 300, '2026-10-12');
        $this->record('expense', 500, '2026-10-12');
        // Bank 500; envelope 300 - 500 = -200; ready 500 - (-200) = 700.
        $this->assertSame('-200.00', $this->envelope($groceries));
        $this->assertSame('700.00', $this->ready());

        $this->post("/planning/buckets/{$groceries->id}/reset")->assertSessionHasNoErrors();

        // 200 comes in from ready: envelope 0; ready 700 - 200 = 500.
        $this->assertSame('0.00', $this->envelope($groceries));
        $this->assertSame('500.00', $this->ready());
    }

    public function test_reset_asks_before_topping_up_more_than_is_ready(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $groceries = $this->groceries();
        $this->fund($groceries, 300, '2026-10-12');
        $this->savings('Trip', 600);
        $this->record('expense', 500, '2026-10-12');
        // Bank 500; groceries -200, trip 600; ready 500 - 400 = 100.
        $this->assertSame('100.00', $this->ready());

        $this->post("/planning/buckets/{$groceries->id}/reset")->assertSessionHasErrors($this->overAssignError(100));
        $this->assertSame('-200.00', $this->envelope($groceries));

        $this->post("/planning/buckets/{$groceries->id}/reset", ['confirm_over_assign' => true])->assertSessionHasNoErrors();
        // Groceries 0, trip 600; ready 500 - 600 = -100.
        $this->assertSame('0.00', $this->envelope($groceries));
        $this->assertSame('-100.00', $this->ready());
    }

    public function test_reset_gives_a_full_bucket_back_to_ready_to_assign(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $trip = $this->savings('Trip', 400);
        $this->assertSame('600.00', $this->ready());

        $this->post("/planning/buckets/{$trip->id}/reset")->assertSessionHasNoErrors();

        $this->assertSame('0.00', $this->envelope($trip));
        $this->assertSame('1000.00', $this->ready());
    }

    public function test_removing_a_move_to_ready_to_assign_is_guarded_like_an_assignment(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $trip = $this->savings('Trip', 900);
        $this->post("/planning/buckets/{$trip->id}/move", ['to_bucket_id' => 'ready', 'amount' => 200])->assertSessionHasNoErrors();
        $this->savings('Car', 250);
        // Trip 700, car 250; ready 1000 - 950 = 50.
        $this->assertSame('50.00', $this->ready());
        $toReady = Allocation::where('bucket_id', $trip->id)->where('amount', -200)->sole();

        // Removing it puts 200 back in the trip, 150 more than is ready.
        $this->delete("/planning/buckets/{$trip->id}/allocations/{$toReady->id}")->assertSessionHasErrors($this->overAssignError(150));
        $this->assertSame('700.00', $this->envelope($trip));

        $this->delete("/planning/buckets/{$trip->id}/allocations/{$toReady->id}", ['confirm_over_assign' => true])->assertSessionHasNoErrors();
        // Trip 900, car 250; ready 1000 - 1150 = -150.
        $this->assertSame('900.00', $this->envelope($trip));
        $this->assertSame('-150.00', $this->ready());
    }

    public function test_removing_a_move_between_buckets_needs_no_confirmation(): void
    {
        $this->bank->update(['initial_balance' => 1000]);
        $trip = $this->savings('Trip', 600);
        $car = $this->savings('Car', 400);
        $this->post("/planning/buckets/{$trip->id}/move", ['to_bucket_id' => $car->id, 'amount' => 100])->assertSessionHasNoErrors();
        $this->assertSame('0.00', $this->ready());
        $out = Allocation::where('bucket_id', $trip->id)->where('source', AllocationSource::Transfer->value)->sole();

        $this->delete("/planning/buckets/{$trip->id}/allocations/{$out->id}")->assertSessionHasNoErrors();

        // Both sides go: trip 600, car 400; ready still 0.
        $this->assertSame('600.00', $this->envelope($trip));
        $this->assertSame('400.00', $this->envelope($car));
        $this->assertSame('0.00', $this->ready());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function spendingPayload(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'spending', 'name' => 'Groceries', 'currency' => 'COP', 'category_id' => $this->food->id, 'cycle' => 'monthly', 'cap_amount' => 5000,
        ], $overrides);
    }
}
