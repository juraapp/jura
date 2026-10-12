<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Services\TransactionService;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\BucketCycle;
use App\Modules\Planning\Services\BucketCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BucketCycleHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $incomeCategory;

    private Category $groceryCategory;

    private Bucket $groceries;

    private Bucket $savings;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $this->incomeCategory = Category::factory()->income()->create(['user_id' => $this->user->id]);
        $this->groceryCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $this->savings = Bucket::factory()->savings()->for($this->user)->create([
            'name' => 'Emergency', 'currency' => 'COP', 'priority' => 10, 'cap_amount' => 1000,
            'fill_rule' => FillRule::FillToTarget, 'tracking_started_on' => '2026-10-01',
        ]);
        $this->groceries = Bucket::factory()->for($this->user)->create([
            'name' => 'Groceries', 'currency' => 'COP', 'priority' => 0, 'category_id' => $this->groceryCategory->id,
            'cap_amount' => 500, 'cycle' => 'monthly', 'fill_rule' => FillRule::FillToTarget,
            'carry_over' => CarryOverPolicy::Overflow, 'overflow_bucket_id' => $this->savings->id,
            'tracking_started_on' => '2026-10-01',
        ]);
    }

    private function receive(string $date, float $amount = 1000): void
    {
        app(TransactionService::class)->createIncome($this->user, [
            'account_id' => $this->account->id, 'category_id' => $this->incomeCategory->id, 'amount' => $amount, 'date' => $date,
        ]);
    }

    private function spend(string $date, float $amount): void
    {
        Transaction::factory()->for($this->user)->for($this->account)->create([
            'category_id' => $this->groceryCategory->id, 'type' => 'expense', 'amount' => $amount, 'date' => $date,
        ]);
    }

    private function funded(Bucket $bucket): float
    {
        return (float) Allocation::where('bucket_id', $bucket->id)->sum('amount');
    }

    private function closeAt(string $date): int
    {
        return app(BucketCycleService::class)->closeDue(Carbon::parse($date));
    }

    public function test_the_month_fills_groceries_and_savings_then_unspent_groceries_overflow_into_savings(): void
    {
        $this->receive('2026-10-01');
        $this->assertSame(500.0, $this->funded($this->groceries));
        $this->assertSame(500.0, $this->funded($this->savings));

        $this->spend('2026-10-10', 100);
        $this->assertSame(1, $this->closeAt('2026-11-02'));

        $this->assertSame(100.0, $this->funded($this->groceries), 'what was spent stays used; the unspent 400 left');
        $this->assertSame(900.0, $this->funded($this->savings), 'savings starts the new month with 900');
    }

    public function test_a_closed_cycle_keeps_a_snapshot_of_how_the_bucket_filled_and_emptied(): void
    {
        $this->receive('2026-10-01');
        $this->spend('2026-10-10', 100);
        $this->closeAt('2026-11-02');

        $groceries = BucketCycle::where('bucket_id', $this->groceries->id)->firstOrFail();
        $this->assertSame('2026-10-01', $groceries->starts_on->toDateString());
        $this->assertSame('2026-10-31', $groceries->ends_on->toDateString());
        $this->assertTrue($groceries->isClosed());
        $this->assertSame(['0.00', '500.00', '100.00', '400.00', '0.00'], [
            $groceries->opening->toDecimalString(), $groceries->filled->toDecimalString(), $groceries->spent->toDecimalString(),
            $groceries->released->toDecimalString(), $groceries->closing->toDecimalString(),
        ]);
        $this->assertSame('500.00', $groceries->cap->toDecimalString());
    }

    public function test_next_months_income_tops_groceries_back_up_and_only_fills_the_missing_savings(): void
    {
        $this->receive('2026-10-01');
        $this->spend('2026-10-10', 100);
        $this->closeAt('2026-11-02');

        Carbon::setTestNow('2026-11-03 08:00:00');
        $this->receive('2026-11-03');

        $this->assertSame(600.0, $this->funded($this->groceries), 'the 100 already used plus a fresh 500');
        $this->assertSame(1000.0, $this->funded($this->savings), 'only the 100 missing to reach its target');
    }

    public function test_every_missed_cycle_is_closed_in_order_and_running_again_does_nothing(): void
    {
        $this->receive('2026-10-01');

        $this->assertSame(2, $this->closeAt('2026-12-15'), 'October and November');
        $this->assertSame(0, $this->closeAt('2026-12-15'));

        $rows = BucketCycle::where('bucket_id', $this->groceries->id)->orderBy('starts_on')->get();
        $this->assertSame(['2026-10-01', '2026-11-01'], $rows->pluck('starts_on')->map->toDateString()->all());
        $this->assertSame('500.00', $rows[0]->filled->toDecimalString());
        $this->assertSame('0.00', $rows[1]->filled->toDecimalString(), 'nothing arrived in November');
    }

    public function test_a_cycle_that_starts_on_another_day_closes_on_its_own_dates(): void
    {
        $this->groceries->update(['cycle_anchor' => '2026-01-15', 'tracking_started_on' => '2026-09-20']);

        $this->closeAt('2026-10-16');

        $rows = BucketCycle::where('bucket_id', $this->groceries->id)->orderBy('starts_on')->get();
        $this->assertSame(['2026-09-15..2026-10-14'], $rows->map(fn ($row) => $row->starts_on->toDateString().'..'.$row->ends_on->toDateString())->all());
    }

    public function test_a_limit_set_for_one_cycle_changes_what_income_tops_up_that_cycle_only(): void
    {
        BucketCycle::create([
            'bucket_id' => $this->groceries->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'open', 'cap_override' => 300,
        ])->forceFill(['user_id' => $this->user->id])->save();

        $this->receive('2026-10-02');

        $this->assertSame(300.0, $this->funded($this->groceries));
        $this->assertSame(700.0, $this->funded($this->savings));
    }
}
