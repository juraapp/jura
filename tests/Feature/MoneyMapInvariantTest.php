<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketCycleService;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * After every kind of money mutation, the money map must agree with an independent recount from the raw tables:
 * in accounts = Σ active on-budget account balances, in buckets = Σ funded envelopes, ready = the difference.
 */
class MoneyMapInvariantTest extends TestCase
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
        $this->bank = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
        $this->salary = Category::factory()->income()->create(['user_id' => $this->user->id]);
        $this->food = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    /* ---------- the independent recount ---------- */

    /**
     * @return array<string, array{inAccounts: string, inBuckets: string, readyToAssign: string}>
     */
    private function recount(): array
    {
        $totals = [];
        $add = function (string $currency, string $key, string $amount) use (&$totals) {
            $totals[$currency] ??= ['inAccounts' => '0.00', 'inBuckets' => '0.00'];
            $totals[$currency][$key] = bcadd($totals[$currency][$key], $amount, 2);
        };

        $accounts = DB::table('accounts')->where('user_id', $this->user->id)->whereNull('deleted_at')
            ->where('is_active', true)->where('on_budget', true)->get();
        foreach ($accounts as $account) {
            $signed = DB::table('transactions')->where('account_id', $account->id)->whereNull('deleted_at')
                ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('income', 'transfer_in') THEN amount ELSE -amount END), 0) AS total")
                ->value('total');
            $add($account->currency, 'inAccounts', bcadd((string) $account->initial_balance, (string) $signed, 2));
        }

        foreach (DB::table('buckets')->where('user_id', $this->user->id)->get() as $bucket) {
            $firstAllocation = DB::table('allocations')->where('bucket_id', $bucket->id)->min('date');
            if ($firstAllocation === null) {
                continue; // never funded: a spending limit only
            }

            $envelope = (string) DB::table('allocations')->where('bucket_id', $bucket->id)->sum('amount');
            if ($bucket->kind === 'spending' && $bucket->category_id !== null) {
                $since = max($bucket->tracking_started_on, $firstAllocation);
                $spent = DB::table('transactions')
                    ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                    ->where('transactions.user_id', $this->user->id)
                    ->where('transactions.type', 'expense')->whereNull('transactions.deleted_at')->whereNull('transactions.bucket_id')
                    ->where('transactions.category_id', $bucket->category_id)->where('transactions.date', '>=', $since)
                    ->where('accounts.currency', $bucket->currency)->where('accounts.on_budget', true)
                    ->sum('transactions.amount');
                $envelope = bcsub($envelope, (string) $spent, 2);
            }
            $add($bucket->currency, 'inBuckets', $envelope);
        }

        ksort($totals);

        return array_map(fn (array $row) => $row + ['readyToAssign' => bcsub($row['inAccounts'], $row['inBuckets'], 2)], $totals);
    }

    private function assertInvariant(?string $expectedReady = null): void
    {
        $map = array_map(fn (array $row) => [
            'inAccounts' => $row['inAccounts']->toDecimalString(),
            'inBuckets' => $row['inBuckets']->toDecimalString(),
            'readyToAssign' => $row['readyToAssign']->toDecimalString(),
        ], app(MoneyMap::class)->summary($this->user->fresh()));

        $this->assertEquals($this->recount(), $map);

        if ($expectedReady !== null) {
            $this->assertSame($expectedReady, $map['COP']['readyToAssign'] ?? '0.00');
        }
    }

    /* ---------- helpers ---------- */

    private function savings(string $name, array $attributes = []): Bucket
    {
        return Bucket::factory()->savings()->for($this->user)->create(array_merge(['name' => $name, 'currency' => 'COP'], $attributes));
    }

    private function fund(Bucket $bucket, int $amount, string $date = '2026-10-12'): void
    {
        app(BucketLedger::class)->credit($bucket, Money::of($amount), AllocationSource::Adjustment, null, Carbon::parse($date));
    }

    private function record(string $type, int $amount, array $extra = []): int
    {
        $this->post('/finances/transactions', array_merge([
            'type' => $type, 'account_id' => $this->bank->id, 'category_id' => $type === 'income' ? $this->salary->id : $this->food->id,
            'amount' => $amount, 'date' => '2026-10-12', 'description' => $type,
        ], $extra))->assertSessionHasNoErrors();

        return Transaction::latest('id')->value('id');
    }

    private function edit(int $id, array $changes): void
    {
        $transaction = Transaction::findOrFail($id);
        $this->patch("/finances/transactions/{$id}", array_merge([
            'account_id' => $transaction->account_id, 'category_id' => $transaction->category_id, 'amount' => $transaction->amount->toDecimalString(),
            'date' => $transaction->date->toDateString(), 'description' => $transaction->description, 'bucket_id' => $transaction->bucket_id,
        ], $changes))->assertSessionHasNoErrors();
    }

    private function offBudgetAccount(int $balance = 0): Account
    {
        return Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP', 'initial_balance' => $balance]);
    }

    /* ---------- scenarios ---------- */

    public function test_income_with_and_without_fill_rules(): void
    {
        $this->assertInvariant('1000.00');
        $this->record('income', 500);
        $this->assertInvariant('1500.00');

        $this->savings('Car', ['fill_rule' => FillRule::Percent, 'fill_value' => 40]);
        $this->record('income', 500);
        $this->assertInvariant('1800.00');
    }

    public function test_expenses_with_and_without_a_bucket_through_edit_delete_restore_and_purge(): void
    {
        $trip = $this->savings('Trip');
        $car = $this->savings('Car');
        $this->fund($trip, 300);
        $this->fund($car, 100);
        $this->assertInvariant('600.00');

        $plain = $this->record('expense', 50);
        $this->assertInvariant('550.00');
        $linked = $this->record('expense', 400, ['bucket_id' => $trip->id]);
        $this->assertInvariant('550.00');

        $this->edit($linked, ['amount' => 450]);
        $this->assertInvariant('550.00');
        $this->edit($linked, ['bucket_id' => $car->id]);
        $this->assertInvariant('550.00');
        $this->edit($linked, ['bucket_id' => null]);
        $this->assertInvariant('100.00');
        $this->edit($linked, ['bucket_id' => $trip->id]);

        $this->delete("/finances/transactions/{$linked}")->assertRedirect();
        $this->assertInvariant('550.00');
        $this->post("/settings/trash/transactions/{$linked}/restore")->assertRedirect();
        $this->assertInvariant('550.00');
        $this->delete("/finances/transactions/{$plain}")->assertRedirect();
        $this->delete("/settings/trash/transactions/{$plain}")->assertRedirect();
        $this->assertInvariant('600.00');
    }

    public function test_a_funded_category_bucket_and_a_limit_only_one(): void
    {
        $other = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $other->id, 'tracking_started_on' => '2026-10-01']);
        $this->fund($groceries, 400);

        $this->record('expense', 150);
        $this->assertInvariant('600.00');
        $this->record('expense', 80, ['category_id' => $other->id]);
        $this->assertInvariant('520.00');
        $this->record('expense', 300);
        $this->assertInvariant('520.00');
    }

    public function test_transfers_between_on_and_off_budget_accounts(): void
    {
        $wallet = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $deposit = $this->offBudgetAccount(2000);
        $transfer = fn (Account $from, Account $to, int $amount) => $this->post('/finances/transactions', [
            'type' => 'transfer', 'from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => $amount, 'date' => '2026-10-12',
        ])->assertSessionHasNoErrors();

        $transfer($this->bank, $wallet, 300);
        $this->assertInvariant('1000.00');
        $transfer($this->bank, $deposit, 200);
        $this->assertInvariant('800.00');
        $transfer($deposit, $wallet, 500);
        $this->assertInvariant('1300.00');
    }

    public function test_off_budget_income_and_category_spending(): void
    {
        $deposit = $this->offBudgetAccount();
        $this->savings('Car', ['fill_rule' => FillRule::Percent, 'fill_value' => 100]);
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        $this->fund($groceries, 200);

        $this->record('income', 700, ['account_id' => $deposit->id]);
        $this->record('expense', 90, ['account_id' => $deposit->id]);
        $this->assertInvariant('800.00');
    }

    public function test_toggling_on_budget_and_archiving_an_account(): void
    {
        $wallet = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 250]);
        $this->assertInvariant('1250.00');

        $wallet->update(['on_budget' => false]);
        $this->assertInvariant('1000.00');
        $wallet->update(['on_budget' => true, 'is_active' => false]);
        $this->assertInvariant('1000.00');
    }

    public function test_opening_adjust_and_assign_with_an_over_assign_confirmation(): void
    {
        $this->post('/planning/buckets', ['kind' => 'savings', 'name' => 'Trip', 'currency' => 'COP', 'opening_balance' => 600])->assertSessionHasNoErrors();
        $trip = Bucket::where('name', 'Trip')->sole();
        $this->assertInvariant('400.00');

        $this->post("/planning/buckets/{$trip->id}/adjust", ['amount' => 500])->assertSessionHasErrors('over_assign');
        $this->assertInvariant('400.00');
        $this->post("/planning/buckets/{$trip->id}/adjust", ['amount' => 500, 'confirm_over_assign' => true])->assertSessionHasNoErrors();
        $this->assertInvariant('-100.00');
        $this->post("/planning/buckets/{$trip->id}/move", ['to_bucket_id' => 'ready', 'amount' => 300])->assertSessionHasNoErrors();
        $this->assertInvariant('200.00');
        $this->post("/planning/buckets/{$trip->id}/move", ['from_ready' => true, 'amount' => 150])->assertSessionHasNoErrors();
        $this->assertInvariant('50.00');
    }

    public function test_an_income_removed_after_its_money_moved_then_restored_and_lowered(): void
    {
        $car = $this->savings('Car', ['fill_rule' => FillRule::Percent, 'fill_value' => 100]);
        $trip = $this->savings('Trip');
        $income = $this->record('income', 500);
        app(BucketLedger::class)->move($car, $trip, Money::of(400));
        $this->assertInvariant('1000.00');

        $this->delete("/finances/transactions/{$income}")->assertRedirect();
        $this->assertInvariant('600.00');
        $this->post("/settings/trash/transactions/{$income}/restore")->assertRedirect();
        $this->assertInvariant('1000.00');
        $this->assertSame('100.00', app(BucketLedger::class)->envelope($car)->toDecimalString());

        $this->edit($income, ['amount' => 200]);
        $this->assertInvariant('800.00');
    }

    public function test_wish_list_purchases_with_without_a_bucket_and_off_budget_and_their_undo(): void
    {
        $bike = $this->savings('Bike');
        $this->fund($bike, 300);
        $purchase = fn (WishlistItem $item, Account $account) => $this->post("/planning/wishlist/{$item->id}/purchase", [
            'account_id' => $account->id, 'category_id' => $this->food->id,
        ])->assertSessionHasNoErrors();
        $withBucket = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 500, 'bucket_id' => $bike->id]);
        $withoutBucket = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => null]);
        $fromDeposit = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 50, 'bucket_id' => $bike->id]);

        $purchase($withBucket, $this->bank);
        $this->assertInvariant('500.00');
        $purchase($withoutBucket, $this->bank);
        $this->assertInvariant('400.00');
        $purchase($fromDeposit, $this->offBudgetAccount(1000));
        $this->assertInvariant('400.00');

        $this->post("/planning/wishlist/{$withBucket->id}/unpurchase")->assertSessionHasNoErrors();
        $this->assertInvariant('600.00');
    }

    public function test_cycle_close_and_completion(): void
    {
        Carbon::setTestNow('2026-11-02 09:00:00');
        $target = $this->savings('Emergency');
        $release = $this->savings('Fun', ['cycle' => 'monthly', 'carry_over' => CarryOverPolicy::Release, 'tracking_started_on' => '2026-10-01']);
        $overflow = $this->savings('Gifts', ['cycle' => 'monthly', 'carry_over' => CarryOverPolicy::Overflow, 'overflow_bucket_id' => $target->id, 'tracking_started_on' => '2026-10-01']);
        $goal = $this->savings('Goal');
        $this->fund($release, 100, '2026-10-05');
        $this->fund($overflow, 200, '2026-10-05');
        $this->fund($goal, 300, '2026-10-05');
        $this->assertInvariant('400.00');

        app(BucketCycleService::class)->closeDue();
        $this->assertInvariant('500.00');

        app(BucketLedger::class)->complete($goal, Carbon::parse('2026-11-02'));
        $this->assertInvariant('800.00');
    }

    public function test_deleting_a_bucket_with_money_or_overspent_forgets_its_expenses(): void
    {
        $trip = $this->savings('Trip');
        $car = $this->savings('Car');
        $this->fund($trip, 300);
        $this->fund($car, 100);
        $hotel = $this->record('expense', 50, ['bucket_id' => $trip->id]);
        $tyres = $this->record('expense', 400, ['bucket_id' => $car->id]);
        $this->assertInvariant('600.00');

        $this->delete("/planning/buckets/{$trip->id}")->assertRedirect();
        $this->assertInvariant('850.00');
        $this->delete("/planning/buckets/{$car->id}")->assertRedirect();
        $this->assertInvariant('550.00');
        $this->assertSame([null, null], Transaction::whereKey([$hotel, $tyres])->pluck('bucket_id')->all());
    }

    public function test_a_future_dated_expense_counts_on_both_sides_at_once(): void
    {
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        $this->fund($groceries, 300);
        $trip = $this->savings('Trip');
        $this->fund($trip, 200);

        $this->record('expense', 120, ['date' => '2026-12-24', 'confirm_future' => 1]);
        $this->record('expense', 80, ['date' => '2026-12-24', 'bucket_id' => $trip->id, 'confirm_future' => 1]);
        $this->assertInvariant('500.00');
    }
}
