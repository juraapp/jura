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
 * After every kind of money mutation, the money map must show the figures worked out by hand for that step (in
 * accounts, in buckets, ready to assign) and agree with an independent recount from the raw tables: in accounts =
 * Σ active on-budget account balances, in buckets = Σ funded envelopes, ready = the difference.
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
            if ($bucket->funded_since === null) {
                continue; // never funded: a spending limit only
            }

            $envelope = (string) DB::table('allocations')->where('bucket_id', $bucket->id)->sum('amount');
            if ($bucket->kind === 'spending' && $bucket->category_id !== null) {
                $since = max($bucket->tracking_started_on, $bucket->funded_since);
                $spent = DB::table('transactions')
                    ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                    ->where('transactions.user_id', $this->user->id)
                    ->where('transactions.type', 'expense')->whereNull('transactions.deleted_at')->whereNull('transactions.bucket_id')
                    ->where('transactions.category_id', $bucket->category_id)->where('transactions.date', '>=', $since)
                    ->where('accounts.currency', $bucket->currency)->where('accounts.on_budget', true)->where('accounts.is_active', true)
                    ->sum('transactions.amount');
                $envelope = bcsub($envelope, (string) $spent, 2);
            }
            $add($bucket->currency, 'inBuckets', $envelope);
        }

        ksort($totals);

        return array_map(fn (array $row) => $row + ['readyToAssign' => bcsub($row['inAccounts'], $row['inBuckets'], 2)], $totals);
    }

    /**
     * The money map shows exactly these hand-worked figures (COP), and they match the independent recount.
     */
    private function assertMoneyMap(string $inAccounts, string $inBuckets, string $ready): void
    {
        $map = array_map(fn (array $row) => [
            'inAccounts' => $row['inAccounts']->toDecimalString(),
            'inBuckets' => $row['inBuckets']->toDecimalString(),
            'readyToAssign' => $row['readyToAssign']->toDecimalString(),
        ], app(MoneyMap::class)->summary($this->user->fresh()));

        $this->assertSame(
            ['inAccounts' => $inAccounts, 'inBuckets' => $inBuckets, 'readyToAssign' => $ready],
            $map['COP'] ?? ['inAccounts' => '0.00', 'inBuckets' => '0.00', 'readyToAssign' => '0.00'],
        );
        $this->assertEquals($this->recount(), $map);
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
        $this->assertMoneyMap('1000.00', '0.00', '1000.00');
        $this->record('income', 500);
        $this->assertMoneyMap('1500.00', '0.00', '1500.00');

        $this->savings('Car', ['fill_rule' => FillRule::Percent, 'fill_value' => 40]);
        $this->record('income', 500);
        $this->assertMoneyMap('2000.00', '200.00', '1800.00');
    }

    public function test_expenses_with_and_without_a_bucket_through_edit_delete_restore_and_purge(): void
    {
        $trip = $this->savings('Trip');
        $car = $this->savings('Car');
        $this->fund($trip, 300);
        $this->fund($car, 100);
        $this->assertMoneyMap('1000.00', '400.00', '600.00');

        $plain = $this->record('expense', 50);
        $this->assertMoneyMap('950.00', '400.00', '550.00');
        $linked = $this->record('expense', 400, ['bucket_id' => $trip->id]);
        $this->assertMoneyMap('550.00', '0.00', '550.00');

        $this->edit($linked, ['amount' => 450]);
        $this->assertMoneyMap('500.00', '-50.00', '550.00');
        $this->edit($linked, ['bucket_id' => $car->id]);
        $this->assertMoneyMap('500.00', '-50.00', '550.00');
        $this->edit($linked, ['bucket_id' => null]);
        $this->assertMoneyMap('500.00', '400.00', '100.00');
        $this->edit($linked, ['bucket_id' => $trip->id]);

        $this->delete("/finances/transactions/{$linked}")->assertRedirect();
        $this->assertMoneyMap('950.00', '400.00', '550.00');
        $this->post("/settings/trash/transactions/{$linked}/restore")->assertRedirect();
        $this->assertMoneyMap('500.00', '-50.00', '550.00');
        $this->delete("/finances/transactions/{$plain}")->assertRedirect();
        $this->delete("/settings/trash/transactions/{$plain}")->assertRedirect();
        $this->assertMoneyMap('550.00', '-50.00', '600.00');
    }

    public function test_a_funded_category_bucket_and_a_limit_only_one(): void
    {
        $other = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $other->id, 'tracking_started_on' => '2026-10-01']);
        $this->fund($groceries, 400);

        $this->record('expense', 150);
        $this->assertMoneyMap('850.00', '250.00', '600.00');
        $this->record('expense', 80, ['category_id' => $other->id]);
        $this->assertMoneyMap('770.00', '250.00', '520.00');
        $this->record('expense', 300);
        $this->assertMoneyMap('470.00', '-50.00', '520.00');
    }

    public function test_transfers_between_on_and_off_budget_accounts(): void
    {
        $wallet = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $deposit = $this->offBudgetAccount(2000);
        $transfer = fn (Account $from, Account $to, int $amount) => $this->post('/finances/transactions', [
            'type' => 'transfer', 'from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => $amount, 'date' => '2026-10-12',
        ])->assertSessionHasNoErrors();

        $transfer($this->bank, $wallet, 300);
        $this->assertMoneyMap('1000.00', '0.00', '1000.00');
        $transfer($this->bank, $deposit, 200);
        $this->assertMoneyMap('800.00', '0.00', '800.00');
        $transfer($deposit, $wallet, 500);
        $this->assertMoneyMap('1300.00', '0.00', '1300.00');
    }

    public function test_off_budget_income_and_category_spending(): void
    {
        $deposit = $this->offBudgetAccount();
        $this->savings('Car', ['fill_rule' => FillRule::Percent, 'fill_value' => 100]);
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        $this->fund($groceries, 200);

        $this->record('income', 700, ['account_id' => $deposit->id]);
        $this->record('expense', 90, ['account_id' => $deposit->id]);
        $this->assertMoneyMap('1000.00', '200.00', '800.00');
    }

    public function test_toggling_on_budget_and_archiving_an_account(): void
    {
        $wallet = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 250]);
        $this->assertMoneyMap('1250.00', '0.00', '1250.00');

        $wallet->update(['on_budget' => false]);
        $this->assertMoneyMap('1000.00', '0.00', '1000.00');
        $wallet->update(['on_budget' => true, 'is_active' => false]);
        $this->assertMoneyMap('1000.00', '0.00', '1000.00');
    }

    public function test_opening_adjust_and_assign_with_an_over_assign_confirmation(): void
    {
        $this->post('/planning/buckets', ['kind' => 'savings', 'name' => 'Trip', 'currency' => 'COP', 'opening_balance' => 600])->assertSessionHasNoErrors();
        $trip = Bucket::where('name', 'Trip')->sole();
        $this->assertMoneyMap('1000.00', '600.00', '400.00');

        $this->post("/planning/buckets/{$trip->id}/adjust", ['amount' => 500])->assertSessionHasErrors('over_assign');
        $this->assertMoneyMap('1000.00', '600.00', '400.00');
        $this->post("/planning/buckets/{$trip->id}/adjust", ['amount' => 500, 'confirm_over_assign' => true])->assertSessionHasNoErrors();
        $this->assertMoneyMap('1000.00', '1100.00', '-100.00');
        $this->post("/planning/buckets/{$trip->id}/move", ['to_bucket_id' => 'ready', 'amount' => 300])->assertSessionHasNoErrors();
        $this->assertMoneyMap('1000.00', '800.00', '200.00');
        $this->post("/planning/buckets/{$trip->id}/move", ['from_ready' => true, 'amount' => 150])->assertSessionHasNoErrors();
        $this->assertMoneyMap('1000.00', '950.00', '50.00');
    }

    public function test_an_income_removed_after_its_money_moved_then_restored_and_lowered(): void
    {
        $car = $this->savings('Car', ['fill_rule' => FillRule::Percent, 'fill_value' => 100]);
        $trip = $this->savings('Trip');
        $income = $this->record('income', 500);
        app(BucketLedger::class)->move($car, $trip, Money::of(400));
        $this->assertMoneyMap('1500.00', '500.00', '1000.00');

        $this->delete("/finances/transactions/{$income}")->assertRedirect();
        $this->assertMoneyMap('1000.00', '400.00', '600.00');
        $this->post("/settings/trash/transactions/{$income}/restore")->assertRedirect();
        $this->assertMoneyMap('1500.00', '500.00', '1000.00');
        $this->assertSame('100.00', app(BucketLedger::class)->envelope($car)->toDecimalString());

        $this->edit($income, ['amount' => 200]);
        $this->assertMoneyMap('1200.00', '400.00', '800.00');
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
        $this->assertMoneyMap('500.00', '0.00', '500.00');
        $purchase($withoutBucket, $this->bank);
        $this->assertMoneyMap('400.00', '0.00', '400.00');
        $purchase($fromDeposit, $this->offBudgetAccount(1000));
        $this->assertMoneyMap('400.00', '0.00', '400.00');

        $this->post("/planning/wishlist/{$withBucket->id}/unpurchase")->assertSessionHasNoErrors();
        $this->assertMoneyMap('900.00', '300.00', '600.00');
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
        $this->assertMoneyMap('1000.00', '600.00', '400.00');

        app(BucketCycleService::class)->closeDue();
        $this->assertMoneyMap('1000.00', '500.00', '500.00');

        app(BucketLedger::class)->complete($goal, Carbon::parse('2026-11-02'));
        $this->assertMoneyMap('1000.00', '200.00', '800.00');
    }

    public function test_deleting_a_bucket_with_money_or_overspent_forgets_its_expenses(): void
    {
        $trip = $this->savings('Trip');
        $car = $this->savings('Car');
        $this->fund($trip, 300);
        $this->fund($car, 100);
        $hotel = $this->record('expense', 50, ['bucket_id' => $trip->id]);
        $tyres = $this->record('expense', 400, ['bucket_id' => $car->id]);
        $this->assertMoneyMap('550.00', '-50.00', '600.00');

        $this->delete("/planning/buckets/{$trip->id}")->assertRedirect();
        $this->assertMoneyMap('550.00', '-300.00', '850.00');
        $this->delete("/planning/buckets/{$car->id}")->assertRedirect();
        $this->assertMoneyMap('550.00', '0.00', '550.00');
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
        $this->assertMoneyMap('800.00', '300.00', '500.00');
    }

    public function test_a_wish_list_purchase_from_a_bucket_in_a_category_with_a_funded_bucket(): void
    {
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        $bike = $this->savings('Bike');
        $this->fund($groceries, 200);
        $this->fund($bike, 300);
        $this->assertMoneyMap('1000.00', '500.00', '500.00');
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'price' => 100, 'bucket_id' => $bike->id]);

        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $this->bank->id, 'category_id' => $this->food->id])->assertSessionHasNoErrors();

        // Bike 200, groceries still 200.
        $this->assertMoneyMap('900.00', '400.00', '500.00');
    }

    public function test_linked_and_category_spending_of_an_account_that_leaves_and_rejoins_the_budget(): void
    {
        $card = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $trip = $this->savings('Trip');
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        $this->fund($trip, 300);
        $this->fund($groceries, 300);
        $this->record('expense', 100, ['account_id' => $card->id, 'bucket_id' => $trip->id]);
        $this->record('expense', 100, ['account_id' => $card->id]);
        // Trip 200, groceries 200.
        $this->assertMoneyMap('800.00', '400.00', '400.00');

        $card->update(['on_budget' => false]);
        $this->assertMoneyMap('1000.00', '600.00', '400.00');
        $card->update(['on_budget' => true]);
        $this->assertMoneyMap('800.00', '400.00', '400.00');
        $card->update(['is_active' => false]);
        $this->assertMoneyMap('1000.00', '600.00', '400.00');
        $card->update(['is_active' => true]);
        $this->assertMoneyMap('800.00', '400.00', '400.00');
    }

    public function test_removing_the_first_income_of_a_category_bucket_keeps_its_earlier_spending(): void
    {
        $groceries = Bucket::factory()->for($this->user)->create([
            'currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-09-01', 'fill_rule' => FillRule::Percent, 'fill_value' => 100,
        ]);
        $first = $this->record('income', 100, ['date' => '2026-09-01']);
        $this->record('expense', 80, ['date' => '2026-09-05']);
        $this->record('income', 50, ['date' => '2026-10-01']);
        // Groceries 100 + 50 - 80 = 70.
        $this->assertMoneyMap('1070.00', '70.00', '1000.00');

        $this->delete("/finances/transactions/{$first}")->assertRedirect();

        // Groceries 50 - 80 = -30, covered back to 0 from ready.
        $this->assertMoneyMap('970.00', '0.00', '970.00');
        $this->assertSame('0.00', app(BucketLedger::class)->envelope($groceries)->toDecimalString());
    }

    public function test_an_opening_on_a_category_bucket_whose_category_was_already_spent(): void
    {
        $groceries = Bucket::factory()->for($this->user)->create(['currency' => 'COP', 'category_id' => $this->food->id, 'tracking_started_on' => '2026-10-01']);
        $this->record('expense', 300, ['date' => '2026-10-05']);
        $this->assertMoneyMap('700.00', '0.00', '700.00');

        app(BucketLedger::class)->setOpeningBalance($groceries, Money::of(400));

        // Dated today: the 10-05 expense stays out of it.
        $this->assertMoneyMap('700.00', '400.00', '300.00');
    }
}
