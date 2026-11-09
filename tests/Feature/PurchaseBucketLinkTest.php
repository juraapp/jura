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
use Tests\TestCase;

/**
 * The bucket link of a wish-list purchase's expense: kept when the account is out of the budget for now, and
 * fixed by the purchase (editing the expense cannot change or drop it). Expected numbers are worked out by hand.
 */
class PurchaseBucketLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $bank;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->bank = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
        $this->food = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    private function savingsBucket(string $name = 'Bike'): Bucket
    {
        return Bucket::factory()->savings()->for($this->user)->create(['name' => $name, 'currency' => 'COP']);
    }

    private function credit(Bucket $bucket, int $amount): void
    {
        app(BucketLedger::class)->credit($bucket, Money::of($amount), AllocationSource::Adjustment, null, null);
    }

    private function envelope(Bucket $bucket): string
    {
        return app(BucketLedger::class)->envelope($bucket->fresh())->toDecimalString();
    }

    private function readyToAssign(): string
    {
        return app(MoneyMap::class)->readyToAssign($this->user->fresh(), 'COP')->toDecimalString();
    }

    public function test_a_purchase_from_an_off_budget_account_charges_the_items_bucket_once_the_account_is_back(): void
    {
        $card = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0, 'on_budget' => false]);
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => $this->user->today()->subDays(10)->toDateString()]);
        $bike = $this->savingsBucket();
        $this->credit($groceries, 200);
        $this->credit($bike, 300);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $bike->id]);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $card->id, 'category_id' => $this->food->id])
            ->assertSessionHasNoErrors();
        // Off budget: nothing was taken from the bike; 1,000 in the account - 200 - 300 earmarked = 500 ready.
        $this->assertSame(['300.00', '200.00', '500.00'], [$this->envelope($bike), $this->envelope($groceries), $this->readyToAssign()]);

        $card->update(['on_budget' => true]);

        // Back in the budget the 100 comes out of the bike (not the groceries): 200, 200; the account is
        // 1,000 - 100 = 900 on budget, so 900 - 400 = 500 ready.
        $this->assertSame(['200.00', '200.00', '500.00'], [$this->envelope($bike), $this->envelope($groceries), $this->readyToAssign()]);
    }

    public function test_the_bucket_of_a_purchases_expense_cannot_be_dropped_by_editing_it(): void
    {
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => $this->user->today()->subDays(10)->toDateString()]);
        $bike = $this->savingsBucket();
        $this->credit($groceries, 200);
        $this->credit($bike, 300);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $bike->id]);
        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $this->bank->id, 'category_id' => $this->food->id])
            ->assertSessionHasNoErrors();
        $transaction = Transaction::sole();
        $edit = [
            'account_id' => $this->bank->id, 'category_id' => $this->food->id, 'amount' => 100,
            'date' => $transaction->date->toDateString(), 'description' => 'Bike',
        ];

        $this->patch("/finances/transactions/{$transaction->id}", $edit + ['bucket_id' => null])->assertSessionHasErrors('bucket_id');
        $this->patch("/finances/transactions/{$transaction->id}", $edit + ['bucket_id' => $groceries->id])->assertSessionHasErrors('bucket_id');

        // Nothing moved: 1,000 - 100 = 900 in the account; bike 200, groceries 200; 900 - 400 = 500 ready.
        $this->assertSame([$bike->id, '200.00', '200.00', '500.00'], [
            $transaction->fresh()->bucket_id, $this->envelope($bike), $this->envelope($groceries), $this->readyToAssign(),
        ]);

        // Keeping it, or leaving it out, is fine.
        $this->patch("/finances/transactions/{$transaction->id}", array_merge($edit, ['bucket_id' => $bike->id, 'description' => 'Bike again']))->assertSessionHasNoErrors();
        $this->patch("/finances/transactions/{$transaction->id}", array_merge($edit, ['description' => 'Bike once more']))->assertSessionHasNoErrors();
        $this->assertSame([$bike->id, 'Bike once more'], [$transaction->fresh()->bucket_id, $transaction->fresh()->description]);
        $this->assertSame('500.00', $this->readyToAssign());
    }

    public function test_the_edit_form_is_told_when_the_bucket_is_locked_by_a_purchase(): void
    {
        $bike = $this->savingsBucket();
        $this->credit($bike, 300);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $bike->id]);
        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $this->bank->id, 'category_id' => $this->food->id]);
        $purchase = Transaction::sole();
        $plain = Transaction::factory()->for($this->user)->create(['account_id' => $this->bank->id, 'category_id' => $this->food->id, 'type' => 'expense']);

        $this->getJson("/finances/transactions/{$purchase->id}/edit-data")->assertOk()->assertJsonPath('bucket_locked', true);
        $this->getJson("/finances/transactions/{$plain->id}/edit-data")->assertOk()->assertJsonPath('bucket_locked', false);
    }
}
