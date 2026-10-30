<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Services\BucketCycleService;
use App\Modules\Planning\Services\BucketLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BucketCycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 08:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function bucket(CarryOverPolicy $policy, array $attributes = []): Bucket
    {
        return Bucket::factory()->savings()->for($this->user)->create(array_merge([
            'currency' => 'COP', 'cycle' => 'monthly', 'carry_over' => $policy,
            'tracking_started_on' => '2026-10-01',
        ], $attributes));
    }

    private function fund(Bucket $bucket, float $amount, string $date = '2026-10-10'): void
    {
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => $amount, 'currency' => $bucket->currency, 'date' => $date]);
    }

    private function funded(Bucket $bucket): float
    {
        return (float) Allocation::where('bucket_id', $bucket->id)->sum('amount');
    }

    /**
     * What a bucket gave back to ready to assign.
     */
    private function returnedToReady(Bucket $bucket): float
    {
        return -(float) Allocation::where('bucket_id', $bucket->id)->where('source', AllocationSource::Ready->value)->sum('amount');
    }

    private function close(): int
    {
        return app(BucketCycleService::class)->closeDue();
    }

    public function test_carry_keeps_the_money_and_marks_the_cycle_closed(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Carry);
        $this->fund($bucket, 300);

        $this->assertSame(1, $this->close());

        $this->assertSame(300.0, $this->funded($bucket));
        $this->assertSame('2026-10-31', $bucket->fresh()->cycle_closed_through->toDateString());
    }

    public function test_release_returns_the_leftover_to_ready_to_assign(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Release);
        $this->fund($bucket, 300);

        $this->close();

        $this->assertSame(0.0, $this->funded($bucket));
        $this->assertSame(300.0, $this->returnedToReady($bucket));
        $row = Allocation::where('source', AllocationSource::Ready->value)->sole();
        $this->assertNull($row->source_id);
        $this->assertSame('2026-11-01', $row->date->toDateString());
        $this->assertSame(1, Bucket::count(), 'no catch-all bucket is created');
    }

    public function test_overflow_moves_the_leftover_to_the_chosen_bucket(): void
    {
        $target = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        $bucket = $this->bucket(CarryOverPolicy::Overflow, ['overflow_bucket_id' => $target->id]);
        $this->fund($bucket, 300);

        $this->close();

        $this->assertSame(0.0, $this->funded($bucket));
        $this->assertSame(300.0, $this->funded($target));
    }

    public function test_only_positive_leftovers_move_and_money_arriving_in_the_new_cycle_stays(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Release);
        $this->fund($bucket, 300, '2026-10-10');
        $this->fund($bucket, 90, '2026-11-01'); // belongs to the new cycle

        $this->close();

        $this->assertSame(90.0, $this->funded($bucket));
        $this->assertSame(300.0, $this->returnedToReady($bucket));

        $overspent = $this->bucket(CarryOverPolicy::Release, ['category_id' => Category::factory()->expense()->create(['user_id' => $this->user->id])->id, 'kind' => 'spending', 'cap_amount' => 100]);
        $this->fund($overspent, 50);
        Transaction::factory()->for($this->user)->for(Account::factory()->for($this->user)->create(['currency' => 'COP']))->create([
            'category_id' => $overspent->category_id, 'type' => 'expense', 'amount' => 80, 'date' => '2026-10-20',
        ]);

        $this->close();

        $this->assertSame(50.0, $this->funded($overspent), 'overspent buckets have nothing to release');
    }

    public function test_spending_in_the_category_reduces_what_is_released(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $bucket = $this->bucket(CarryOverPolicy::Release, ['kind' => 'spending', 'category_id' => $category->id, 'cap_amount' => 500]);
        $this->fund($bucket, 500);
        Transaction::factory()->for($this->user)->for(Account::factory()->for($this->user)->create(['currency' => 'COP']))->create([
            'category_id' => $category->id, 'type' => 'expense', 'amount' => 200, 'date' => '2026-10-20',
        ]);

        $this->close();

        $this->assertSame(300.0, $this->returnedToReady($bucket));
        $this->assertSame(200.0, $this->funded($bucket));
    }

    public function test_running_twice_does_not_move_money_twice(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Release);
        $this->fund($bucket, 300);

        $this->assertSame(1, $this->close());
        $this->assertSame(0, $this->close());

        $this->assertSame(300.0, $this->returnedToReady($bucket));
    }

    public function test_buckets_without_a_cycle_or_created_in_the_current_cycle_are_skipped(): void
    {
        $noCycle = $this->bucket(CarryOverPolicy::Release, ['cycle' => null]);
        $fresh = $this->bucket(CarryOverPolicy::Release, ['tracking_started_on' => '2026-11-01']);
        $this->fund($noCycle, 100);
        $this->fund($fresh, 100, '2026-11-01');

        $this->assertSame(0, $this->close());
        $this->assertSame(100.0, $this->funded($noCycle));
        $this->assertSame(100.0, $this->funded($fresh));
    }

    public function test_yearly_buckets_close_in_the_new_year_only(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Carry, ['cycle' => 'yearly', 'tracking_started_on' => '2025-03-01']);

        $this->assertSame(1, $this->close(), 'the 2025 cycle ended on 2025-12-31');
        $this->assertSame('2025-12-31', $bucket->fresh()->cycle_closed_through->toDateString());

        Carbon::setTestNow('2027-01-03');
        $this->assertSame(1, $this->close());
        $this->assertSame('2026-12-31', $bucket->fresh()->cycle_closed_through->toDateString());
    }

    public function test_the_command_closes_due_cycles(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Carry);

        $this->artisan('planning:close-bucket-cycles')->assertSuccessful();

        $this->assertNotNull($bucket->fresh()->cycle_closed_through);
    }

    public function test_completing_a_bucket_without_an_overflow_target_returns_its_money_to_ready_to_assign(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Carry, ['cycle' => null]);
        $this->fund($bucket, 250);

        app(BucketLedger::class)->complete($bucket, Carbon::parse('2026-11-02'));

        $this->assertSame(0.0, $this->funded($bucket));
        $this->assertSame(250.0, $this->returnedToReady($bucket));
        $this->assertSame('2026-11-02', $bucket->fresh()->completed_on->toDateString());
    }

    public function test_purchases_paid_from_a_bucket_count_as_spent_in_its_cycle(): void
    {
        $bucket = $this->bucket(CarryOverPolicy::Carry);
        $this->fund($bucket, 500);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => -120, 'currency' => 'COP', 'date' => '2026-10-15', 'source' => AllocationSource::Spend]);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => -30, 'currency' => 'COP', 'date' => '2026-10-16', 'source' => AllocationSource::Ready]);

        $figures = app(BucketCycleService::class)->figures($bucket, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame('120.00', $figures['spent']->toDecimalString());
        $this->assertSame('30.00', $figures['moved_out']->toDecimalString());
    }
}
