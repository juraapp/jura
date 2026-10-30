<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Notifications\Notifications\BudgetThresholdReached;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Jobs\GenerateDueRecurringTransactions;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\BucketCycle;
use App\Modules\Planning\Models\RecurringTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Console commands and queued jobs run with no authenticated user, so the BelongsToUser global scope is
 * off there. Everything they read must still be scoped to the bucket or template's own user.
 */
class BackgroundTenancyTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Category $shared;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shared = Category::factory()->expense()->system()->create();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();

        $bobAccount = Account::factory()->for($this->bob)->create(['currency' => 'COP']);
        Transaction::factory()->for($this->bob)->for($bobAccount)->create([
            'type' => TransactionType::Expense, 'category_id' => $this->shared->id, 'amount' => 700000, 'date' => '2026-10-15',
        ]);
    }

    public function test_closing_cycles_from_the_console_ignores_other_users_spending(): void
    {
        Carbon::setTestNow('2026-11-02 00:30:00');
        $bucket = Bucket::factory()->for($this->alice)->create([
            'category_id' => $this->shared->id, 'currency' => 'COP', 'cycle' => 'monthly', 'cap_amount' => 1000000,
            'carry_over' => CarryOverPolicy::Release, 'tracking_started_on' => '2026-10-01',
        ]);
        Allocation::factory()->for($this->alice)->for($bucket)->create(['amount' => 1000000, 'currency' => 'COP', 'date' => '2026-10-01']);

        $this->assertFalse(Auth::check());
        $this->artisan('planning:close-bucket-cycles')->assertSuccessful();

        $cycle = BucketCycle::forUser($this->alice)->where('bucket_id', $bucket->id)->sole();
        $this->assertSame('0.00', $cycle->spent->toDecimalString());
        $this->assertSame('1000000.00', $cycle->released->toDecimalString());
    }

    public function test_the_recurring_job_does_not_alert_a_user_about_another_users_spending(): void
    {
        Carbon::setTestNow('2026-10-20 06:00:00');
        $aliceAccount = Account::factory()->for($this->alice)->create(['currency' => 'COP']);
        Bucket::factory()->for($this->alice)->create([
            'category_id' => $this->shared->id, 'currency' => 'COP', 'cycle' => 'monthly', 'cap_amount' => 100000,
            'tracking_started_on' => '2026-10-01',
        ]);
        RecurringTransaction::factory()->for($this->alice)->for($aliceAccount)->create([
            'category_id' => Category::factory()->expense()->create(['user_id' => $this->alice->id])->id,
            'type' => TransactionType::Expense, 'amount' => 1000, 'next_due_date' => '2026-10-20',
        ]);

        $this->assertFalse(Auth::check());
        app()->call([new GenerateDueRecurringTransactions, 'handle']);

        $this->assertSame(1, Transaction::forUser($this->alice)->count());
        $this->assertSame(0, $this->alice->notifications()->where('type', BudgetThresholdReached::class)->count());
    }

    public function test_a_recurring_income_fills_to_target_with_room_from_the_owners_spending_only(): void
    {
        Carbon::setTestNow('2026-10-20 06:00:00');
        $aliceAccount = Account::factory()->for($this->alice)->create(['currency' => 'COP']);
        $bucket = Bucket::factory()->for($this->alice)->rule(FillRule::FillToTarget)->create([
            'category_id' => $this->shared->id, 'currency' => 'COP', 'cap_amount' => 500000, 'tracking_started_on' => '2026-10-01',
        ]);
        RecurringTransaction::factory()->income()->for($this->alice)->for($aliceAccount)->create([
            'category_id' => Category::factory()->income()->create(['user_id' => $this->alice->id])->id,
            'amount' => 2000000, 'next_due_date' => '2026-10-20',
        ]);

        app()->call([new GenerateDueRecurringTransactions, 'handle']);

        $this->assertSame('500000.00', number_format((float) Allocation::forUser($this->alice)->where('bucket_id', $bucket->id)->sum('amount'), 2, '.', ''));
    }
}
