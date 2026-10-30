<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A funded spending bucket's envelope shrinks with its category's on-budget spending since it was funded.
 */
class BucketEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    private Bucket $bucket;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 09:00:00');

        $this->user = User::factory()->create();
        $this->category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $this->bucket = Bucket::factory()->for($this->user)->create([
            'category_id' => $this->category->id, 'currency' => 'COP', 'tracking_started_on' => '2026-10-01',
        ]);
    }

    private function spend(int $amount, string $date, ?Account $account = null): void
    {
        $account ??= Account::factory()->for($this->user)->create(['currency' => 'COP']);
        Transaction::factory()->for($this->user)->for($account)->create(['category_id' => $this->category->id, 'amount' => $amount, 'date' => $date]);
    }

    private function fund(int $amount, string $date): void
    {
        app(BucketLedger::class)->credit($this->bucket, Money::of($amount), AllocationSource::Adjustment, null, Carbon::parse($date));
    }

    public function test_a_future_dated_expense_comes_out_of_the_envelope_at_once(): void
    {
        $this->fund(500, '2026-10-01');
        $this->spend(200, '2026-11-20');

        $ledger = app(BucketLedger::class);
        $this->assertSame('300.00', $ledger->envelope($this->bucket)->toDecimalString());
        $this->assertSame('500.00', $ledger->available($this->bucket, Carbon::parse('2026-10-31'))->toDecimalString());
    }

    public function test_spending_before_the_bucket_was_funded_does_not_count(): void
    {
        $this->spend(80, '2026-10-05');
        $this->fund(500, '2026-10-10');
        $this->spend(100, '2026-10-12');

        $ledger = app(BucketLedger::class);
        $this->assertTrue($ledger->isFunded($this->bucket));
        $this->assertSame('2026-10-10', $ledger->fundedSince($this->bucket)->toDateString());
        $this->assertSame('400.00', $ledger->envelope($this->bucket)->toDecimalString());
    }

    public function test_off_budget_spending_does_not_shrink_the_envelope(): void
    {
        $this->fund(500, '2026-10-01');
        $this->spend(100, '2026-10-05', Account::factory()->for($this->user)->offBudget()->create(['currency' => 'COP']));

        $this->assertSame('500.00', app(BucketLedger::class)->envelope($this->bucket)->toDecimalString());
    }

    public function test_an_unfunded_bucket_has_no_funding_date(): void
    {
        $this->assertFalse(app(BucketLedger::class)->isFunded($this->bucket));
        $this->assertNull(app(BucketLedger::class)->fundedSince($this->bucket));
    }
}
