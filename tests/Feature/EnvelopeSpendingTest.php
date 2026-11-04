<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Which envelope an expense comes out of (a linked bucket, a wish-list purchase's bucket or its category's
 * bucket), never two at once. Every expected figure is worked out by hand in the comments.
 */
class EnvelopeSpendingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $bank;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 09:00:00');

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->bank = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
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

    private function groceries(): Bucket
    {
        return Bucket::factory()->for($this->user)->create([
            'name' => 'Groceries', 'currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01',
        ]);
    }

    private function savings(string $name): Bucket
    {
        return Bucket::factory()->savings()->for($this->user)->create(['name' => $name, 'currency' => 'COP']);
    }

    private function fund(Bucket $bucket, int $amount): void
    {
        app(BucketLedger::class)->credit($bucket, Money::of($amount), AllocationSource::Adjustment);
    }

    private function buy(WishlistItem $item, ?Account $account = null): void
    {
        $this->post("/planning/wishlist/{$item->id}/purchase", [
            'account_id' => ($account ?? $this->bank)->id, 'category_id' => $this->food->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_a_purchase_paid_from_a_bucket_does_not_also_come_out_of_its_categorys_envelope(): void
    {
        $groceries = $this->groceries();
        $bike = $this->savings('Bike');
        $this->fund($groceries, 200);
        $this->fund($bike, 300);
        $this->assertSame('500.00', $this->ready());
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $bike->id]);

        $this->buy($item);

        // Bank 900; bike 300 - 100 = 200; groceries untouched 200; ready 900 - 400 = 500.
        $this->assertSame($bike->id, Transaction::sole()->bucket_id);
        $this->assertSame('200.00', $this->envelope($bike));
        $this->assertSame('200.00', $this->envelope($groceries));
        $this->assertSame('500.00', $this->ready());

        $this->post("/planning/wishlist/{$item->id}/unpurchase")->assertSessionHasNoErrors();

        // Bank 1000; bike 300; groceries 200; ready 500.
        $this->assertSame('300.00', $this->envelope($bike));
        $this->assertSame('200.00', $this->envelope($groceries));
        $this->assertSame('500.00', $this->ready());
    }

    public function test_a_purchase_the_bucket_only_partly_covers_takes_the_rest_from_ready_to_assign(): void
    {
        $groceries = $this->groceries();
        $bike = $this->savings('Bike');
        $this->fund($groceries, 200);
        $this->fund($bike, 60);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $bike->id]);

        $this->buy($item);

        // Bank 900; bike pays its 60 and is empty; groceries 200; ready 900 - 200 = 700 (740 before, 40 less).
        $this->assertSame('0.00', $this->envelope($bike));
        $this->assertSame('200.00', $this->envelope($groceries));
        $this->assertSame('700.00', $this->ready());
    }

    public function test_a_purchase_from_an_off_budget_account_is_not_linked_to_the_bucket(): void
    {
        $deposit = Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP', 'initial_balance' => 500]);
        $bike = $this->savings('Bike');
        $this->fund($bike, 300);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $bike->id]);

        $this->buy($item, $deposit);

        // Paid with unbudgeted money: bike keeps 300; bank 1000; ready 700.
        $this->assertNull(Transaction::sole()->bucket_id);
        $this->assertSame('300.00', $this->envelope($bike));
        $this->assertSame('700.00', $this->ready());
    }

    public function test_undoing_a_purchase_that_completed_its_bucket_takes_back_the_released_leftover(): void
    {
        $this->bank->update(['initial_balance' => 2000]);
        $fund = $this->savings('Purchase fund');
        $fund->update(['completes_on_purchase' => true]);
        $this->fund($fund, 1200);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 1000, 'bucket_id' => $fund->id]);
        $this->assertSame('800.00', $this->ready());

        $this->buy($item);

        // The fund pays 1000 and releases its 200 leftover: bank 1000, fund 0, ready 1000.
        $this->assertNotNull($fund->fresh()->completed_on);
        $this->assertSame('0.00', $this->envelope($fund));
        $this->assertSame('1000.00', $this->ready());

        $this->post("/planning/wishlist/{$item->id}/unpurchase")->assertSessionHasNoErrors();

        // Exactly as before: bank 2000, fund 1200 (reopened), ready 800.
        $this->assertNull($fund->fresh()->completed_on);
        $this->assertSame('1200.00', $this->envelope($fund));
        $this->assertSame('800.00', $this->ready());
    }

    public function test_undoing_a_purchase_takes_back_only_what_the_overflow_bucket_still_holds(): void
    {
        $this->bank->update(['initial_balance' => 2000]);
        $restoration = $this->savings('Restoration');
        $fund = $this->savings('Purchase fund');
        $fund->update(['completes_on_purchase' => true, 'carry_over' => 'overflow', 'overflow_bucket_id' => $restoration->id]);
        $this->fund($fund, 1200);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 1000, 'bucket_id' => $fund->id]);

        $this->buy($item);
        // Fund 0, its 200 leftover went to restoration; bank 1000; ready 800.
        $this->assertSame('200.00', $this->envelope($restoration));
        $this->assertSame('800.00', $this->ready());
        $this->post("/planning/buckets/{$restoration->id}/move", ['to_bucket_id' => 'ready', 'amount' => 150])->assertSessionHasNoErrors();
        // Restoration 50; ready 950.

        $this->post("/planning/wishlist/{$item->id}/unpurchase")->assertSessionHasNoErrors();

        // Restoration gives back the 50 it still has, the fund gets 1000 + 50; bank 2000; ready 2000 - 1050 = 950.
        $this->assertSame('0.00', $this->envelope($restoration));
        $this->assertSame('1050.00', $this->envelope($fund));
        $this->assertSame('950.00', $this->ready());
    }
}
