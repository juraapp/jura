<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What happens to a realized plan or a wish-list purchase when its real transaction is deleted, restored
 * from the Trash or purged.
 */
class LinkedRecordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 10000000]);
        $this->expenseCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    }

    private function realizedPlan(): PlannedEvent
    {
        $event = PlannedEvent::factory()->for($this->user)->create([
            'type' => 'expense', 'amount' => 300, 'currency' => 'COP', 'date' => '2026-10-10',
        ]);

        $this->post("/planning/cash-flow/{$event->id}/realize", [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
        ])->assertSessionHasNoErrors();

        return $event->fresh();
    }

    /**
     * @return array{0: WishlistItem, 1: Bucket}
     */
    private function purchasedItem(): array
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => 1000, 'currency' => 'COP']);
        $item = WishlistItem::factory()->for($this->user)->create(['bucket_id' => $bucket->id, 'price' => 400, 'currency' => 'COP']);

        $this->post("/planning/wishlist/{$item->id}/purchase", [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
        ])->assertSessionHasNoErrors();

        return [$item->fresh(), $bucket];
    }

    private function funded(Bucket $bucket): float
    {
        return (float) Allocation::where('bucket_id', $bucket->id)->sum('amount');
    }

    public function test_deleting_the_transaction_of_a_realized_plan_reopens_it_and_restoring_closes_it_again(): void
    {
        $event = $this->realizedPlan();
        $transactionId = $event->realized_transaction_id;

        $this->delete("/finances/transactions/{$transactionId}")->assertRedirect();

        $this->assertSame(PlannedEventStatus::Planned, $event->fresh()->status);
        $this->assertSame($transactionId, $event->fresh()->realized_transaction_id);

        $this->post("/settings/trash/transactions/{$transactionId}/restore")->assertRedirect();

        $this->assertSame(PlannedEventStatus::Realized, $event->fresh()->status);
    }

    public function test_bulk_delete_and_its_undo_also_reopen_and_close_the_plan(): void
    {
        $event = $this->realizedPlan();
        $transactionId = $event->realized_transaction_id;

        $this->post('/finances/transactions/bulk-delete', ['ids' => [$transactionId]])->assertRedirect();
        $this->assertSame(PlannedEventStatus::Planned, $event->fresh()->status);

        $this->post('/finances/transactions/bulk-restore', ['ids' => [$transactionId]])->assertRedirect();
        $this->assertSame(PlannedEventStatus::Realized, $event->fresh()->status);
    }

    public function test_restoring_does_not_touch_a_plan_realized_again_in_the_meantime(): void
    {
        $event = $this->realizedPlan();
        $oldTransactionId = $event->realized_transaction_id;
        $this->delete("/finances/transactions/{$oldTransactionId}");

        $this->post("/planning/cash-flow/{$event->id}/realize", [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
        ])->assertSessionHasNoErrors();
        $newTransactionId = $event->fresh()->realized_transaction_id;

        $this->post("/settings/trash/transactions/{$oldTransactionId}/restore")->assertRedirect();

        $this->assertNotSame($oldTransactionId, $newTransactionId);
        $this->assertSame(PlannedEventStatus::Realized, $event->fresh()->status);
        $this->assertSame($newTransactionId, $event->fresh()->realized_transaction_id);
    }

    public function test_purging_the_transaction_forgets_the_link_and_leaves_the_plan_open(): void
    {
        $event = $this->realizedPlan();
        $transactionId = $event->realized_transaction_id;
        $this->delete("/finances/transactions/{$transactionId}");

        $this->delete("/settings/trash/transactions/{$transactionId}")->assertRedirect();

        $this->assertSame(PlannedEventStatus::Planned, $event->fresh()->status);
        $this->assertNull($event->fresh()->realized_transaction_id);
    }

    public function test_a_realized_plan_still_cannot_be_reopened_directly(): void
    {
        $event = $this->realizedPlan();

        $this->post("/planning/cash-flow/{$event->id}/reopen")->assertSessionHas('error');

        $this->assertSame(PlannedEventStatus::Realized, $event->fresh()->status);
    }

    public function test_deleting_a_purchase_expense_returns_the_money_and_restoring_it_buys_the_item_again(): void
    {
        [$item, $bucket] = $this->purchasedItem();
        $transactionId = $item->purchased_transaction_id;
        $this->assertSame(600.0, $this->funded($bucket));

        $this->delete("/finances/transactions/{$transactionId}")->assertRedirect();

        $item->refresh();
        $this->assertSame(WishlistStatus::Wanted, $item->status);
        $this->assertNull($item->purchase_allocation_id);
        $this->assertSame(1000.0, $this->funded($bucket));

        $this->post("/settings/trash/transactions/{$transactionId}/restore")->assertRedirect();

        $item->refresh();
        $this->assertSame(WishlistStatus::Purchased, $item->status);
        $this->assertSame($transactionId, $item->purchased_transaction_id);
        $this->assertSame('2026-10-12', $item->purchased_on->toDateString());
        $this->assertSame(600.0, $this->funded($bucket));
    }

    public function test_purging_a_deleted_purchase_expense_forgets_the_link(): void
    {
        [$item, $bucket] = $this->purchasedItem();
        $transactionId = $item->purchased_transaction_id;
        $this->delete("/finances/transactions/{$transactionId}");

        $this->delete("/settings/trash/transactions/{$transactionId}")->assertRedirect();

        $this->assertNull($item->fresh()->purchased_transaction_id);
        $this->assertSame(WishlistStatus::Wanted, $item->fresh()->status);
        $this->assertSame(1000.0, $this->funded($bucket));
    }

    public function test_undoing_a_purchase_removes_the_expense_for_good_so_it_cannot_be_restored(): void
    {
        [$item, $bucket] = $this->purchasedItem();

        $this->post("/planning/wishlist/{$item->id}/unpurchase")->assertSessionHas('success');

        $this->assertSame(0, Transaction::withTrashed()->count());
        $this->assertSame(WishlistStatus::Wanted, $item->fresh()->status);
        $this->assertSame(1000.0, $this->funded($bucket));
    }
}
