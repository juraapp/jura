<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Events\PlannedPostingFailed;
use App\Modules\Planning\Jobs\PostDuePlannedEvents;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Planning\Services\SeriesPoster;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AutoPostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Account, 2: Category}
     */
    private function owner(string $timezone = 'UTC'): array
    {
        $user = User::factory()->create(['timezone' => $timezone]);
        $account = Account::factory()->for($user)->create(['currency' => 'COP']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        return [$user, $account, $category];
    }

    private function plan(User $user, Account $account, Category $category, string $date, array $attributes = []): PlannedEvent
    {
        return PlannedEvent::factory()->for($user)->expense()->create($attributes + [
            'date' => $date, 'amount' => 30, 'currency' => 'COP', 'account_id' => $account->id, 'category_id' => $category->id,
            'description' => 'Gym', 'auto_post' => true,
        ]);
    }

    private function postDue(): int
    {
        return app(SeriesPoster::class)->postDue();
    }

    public function test_a_due_plan_is_recorded_on_its_date_as_auto_posted_and_linked(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        [$user, $account, $category] = $this->owner();
        $due = $this->plan($user, $account, $category, '2026-11-10');
        $later = $this->plan($user, $account, $category, '2026-11-11');

        $this->assertSame(1, $this->postDue());

        $transaction = Transaction::withoutGlobalScopes()->sole();
        $this->assertTrue($transaction->is_recurring_generated);
        $this->assertSame('2026-11-10', $transaction->date->toDateString());
        $this->assertSame('30.00', $transaction->amount->toDecimalString());
        $this->assertSame($account->id, $transaction->account_id);
        $this->assertSame(PlannedEventStatus::Realized, $due->fresh()->status);
        $this->assertSame($transaction->id, $due->fresh()->realized_transaction_id);
        $this->assertSame(PlannedEventStatus::Planned, $later->fresh()->status);
    }

    public function test_every_missed_occurrence_is_recorded_in_one_run_on_its_own_date(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        [$user, $account, $category] = $this->owner();
        foreach (['2026-09-10', '2026-10-10', '2026-11-10'] as $date) {
            $this->plan($user, $account, $category, $date);
        }

        $this->assertSame(3, $this->postDue());
        $this->assertSame(['2026-09-10', '2026-10-10', '2026-11-10'], Transaction::withoutGlobalScopes()->orderBy('date')->get()->map(fn ($t) => $t->date->toDateString())->all());
    }

    public function test_a_plan_is_due_by_its_owners_date(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00'); // Auckland: Nov 11, 01:00. Los Angeles: Nov 10, 04:00.
        [$auckland, $aAccount, $aCategory] = $this->owner('Pacific/Auckland');
        [$angeles, $lAccount, $lCategory] = $this->owner('America/Los_Angeles');
        $aucklandPlan = $this->plan($auckland, $aAccount, $aCategory, '2026-11-11');
        $angelesPlan = $this->plan($angeles, $lAccount, $lCategory, '2026-11-11');

        $this->assertSame(1, $this->postDue());
        $this->assertSame(PlannedEventStatus::Realized, $aucklandPlan->fresh()->status);
        $this->assertSame(PlannedEventStatus::Planned, $angelesPlan->fresh()->status);
    }

    public function test_posting_is_idempotent_across_runs_and_against_a_confirm_from_the_web(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        [$user, $account, $category] = $this->owner();
        $plan = $this->plan($user, $account, $category, '2026-11-10');
        $confirmed = $this->plan($user, $account, $category, '2026-11-09');

        $this->actingAs($user)->post("/planning/cash-flow/{$confirmed->id}/realize")->assertSessionHas('success');
        $this->assertSame(1, $this->postDue());
        $this->assertSame(0, $this->postDue());
        $this->actingAs($user)->post("/planning/cash-flow/{$plan->id}/realize")->assertSessionHas('error');

        $this->assertSame(2, Transaction::count());
        $this->assertNull(app(PlannedEventService::class)->realize($plan));
        $this->assertSame(2, Transaction::count());
    }

    public function test_a_plan_whose_account_or_category_is_gone_is_flagged_once_and_the_batch_continues(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        Event::fake([PlannedPostingFailed::class]);
        Log::spy();
        [$user, $account, $category] = $this->owner();
        [$other, $otherAccount, $otherCategory] = $this->owner();
        $archived = $this->plan($user, $account, $category, '2026-11-10');
        $account->update(['is_active' => false]);
        $deletedCategory = Category::factory()->expense()->create(['user_id' => $user->id]);
        $activeAccount = Account::factory()->for($user)->create(['currency' => 'COP']);
        $missingCategory = $this->plan($user, $activeAccount, $deletedCategory, '2026-11-10');
        $deletedCategory->delete();
        $fine = $this->plan($other, $otherAccount, $otherCategory, '2026-11-10');

        $this->assertSame(1, $this->postDue());
        $this->assertSame(0, $this->postDue(), 'retried, still blocked');

        $this->assertSame('account_inactive', $archived->fresh()->post_error);
        $this->assertSame('category_missing', $missingCategory->fresh()->post_error);
        $this->assertSame(PlannedEventStatus::Realized, $fine->fresh()->status);
        Event::assertDispatchedTimes(PlannedPostingFailed::class, 2);
        Event::assertDispatched(PlannedPostingFailed::class, fn (PlannedPostingFailed $event) => $event->user->is($user) && $event->plannedEventId === $archived->id && $event->reason === 'account_inactive');
        Log::shouldHaveReceived('warning')->with('Skipped an auto-posting plan.', Mockery::type('array'))->atLeast()->times(2);

        $account->update(['is_active' => true]);
        $this->assertSame(1, $this->postDue());
        $this->assertNull($archived->fresh()->post_error);
    }

    public function test_a_failure_is_reported_and_the_batch_continues(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        Exceptions::fake();
        [$user, $account, $category] = $this->owner();
        $failing = $this->plan($user, $account, $category, '2026-11-10', ['description' => 'Boom']);
        $working = $this->plan($user, $account, $category, '2026-11-10');
        $real = app(RecordsTransactions::class);
        $this->app->instance(RecordsTransactions::class, new class($real) implements RecordsTransactions
        {
            public function __construct(private RecordsTransactions $real) {}

            public function record(User $user, TransactionType $type, array $data, bool $autoPosted = false): int
            {
                if ($data['description'] === 'Boom') {
                    throw new RuntimeException('boom');
                }

                return $this->real->record($user, $type, $data, $autoPosted);
            }

            public function recordTransfer(User $user, array $data, bool $autoPosted = false): int
            {
                return $this->real->recordTransfer($user, $data, $autoPosted);
            }

            public function recordRecurring(User $user, TransactionType $type, array $data, int $recurringTransactionId): void {}

            public function forget(User $user, int $transactionId): void {}
        });
        $this->app->forgetInstance(PlannedEventService::class);
        $this->app->forgetInstance(SeriesPoster::class);

        $this->assertSame(1, $this->postDue());

        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame('failed', $failing->fresh()->post_error);
        $this->assertSame(PlannedEventStatus::Planned, $failing->fresh()->status);
        $this->assertSame(PlannedEventStatus::Realized, $working->fresh()->status);
    }

    public function test_month_end_occurrences_post_on_the_last_day_of_each_month(): void
    {
        Carbon::setTestNow('2026-01-20 09:00:00');
        [$user, $account, $category] = $this->owner();
        $this->actingAs($user)->post('/planning/cash-flow', [
            'type' => 'expense', 'description' => 'Rent', 'amount' => 500, 'date' => '2026-01-31', 'frequency' => 'monthly',
            'certainty' => 'confirmed', 'account_id' => $account->id, 'category_id' => $category->id, 'auto_post' => true,
        ])->assertSessionHasNoErrors();
        auth()->logout();

        Carbon::setTestNow('2026-03-31 09:00:00');
        $this->assertSame(3, $this->postDue());

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], Transaction::withoutGlobalScopes()->orderBy('date')->get()->map(fn ($t) => $t->date->toDateString())->all());
    }

    public function test_a_one_off_auto_post_plan_dated_today_is_recorded_when_saved(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        [$user, $account, $category] = $this->owner();

        $this->actingAs($user)->post('/planning/cash-flow', [
            'type' => 'expense', 'description' => 'Fee', 'amount' => 12, 'date' => '2026-11-10', 'certainty' => 'confirmed',
            'account_id' => $account->id, 'category_id' => $category->id, 'auto_post' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(PlannedEventStatus::Realized, PlannedEvent::sole()->status);
        $this->assertTrue(Transaction::sole()->is_recurring_generated);
    }

    public function test_an_auto_posted_income_uses_its_bucket_split_within_its_owner_only(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        $user = User::factory()->create(['timezone' => 'UTC']);
        $account = Account::factory()->for($user)->create(['currency' => 'COP']);
        $category = Category::factory()->income()->create(['user_id' => $user->id]);
        $bucket = Bucket::factory()->for($user)->create(['currency' => 'COP']);
        $foreignBucket = Bucket::factory()->create(['currency' => 'COP']);
        $series = PlannedEventSeries::factory()->for($user)->create([
            'type' => 'income', 'amount' => 1000, 'currency' => 'COP', 'account_id' => $account->id, 'category_id' => $category->id,
            'frequency' => RecurringFrequency::Monthly, 'starts_on' => '2026-11-10', 'auto_post' => true,
            'allocation_overrides' => [['bucket_id' => $bucket->id, 'amount' => '200.00'], ['bucket_id' => $foreignBucket->id, 'amount' => '300.00']],
        ]);
        app(PlannedEventService::class)->materialize($series, Carbon::parse('2026-11-30'));

        $this->assertSame(1, $this->postDue());

        $this->assertSame('200.00', number_format((float) Allocation::withoutGlobalScopes()->where('bucket_id', $bucket->id)->sum('amount'), 2, '.', ''));
        $this->assertSame(0, Allocation::withoutGlobalScopes()->where('bucket_id', $foreignBucket->id)->count());
    }

    public function test_the_job_is_unique_and_runs_the_poster(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        [$user, $account, $category] = $this->owner();
        $this->plan($user, $account, $category, '2026-11-10');

        $this->assertInstanceOf(ShouldBeUnique::class, new PostDuePlannedEvents);
        PostDuePlannedEvents::dispatch();

        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
    }
}
