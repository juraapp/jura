<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Notifications\Notifications\UpcomingRecurringPayment;
use App\Modules\Planning\Jobs\GenerateDueRecurringTransactions;
use App\Modules\Planning\Models\RecurringTransaction;
use App\Modules\Planning\Services\RecurringTransactionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class RecurringTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_is_displayed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        RecurringTransaction::factory()->for($user)->for($account)->for($category)->count(2)->create();
        RecurringTransaction::factory()->create(); // another user's

        $response = $this->actingAs($user)->get('/planning/recurring');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('RecurringTransactions/Index')
            ->has('recurrences', 2)
        );
    }

    public function test_user_can_create_a_recurring_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 45000,
            'description' => 'Netflix',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDays(5)->toDateString(),
            'end_date' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $user->id,
            'description' => 'Netflix',
            'is_active' => true,
        ]);
    }

    public function test_creating_a_recurring_transaction_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'transfer',
            'account_id' => 999999,
            'category_id' => 999999,
            'amount' => -5,
            'frequency' => 'daily',
            'next_due_date' => '',
        ]);

        $response->assertSessionHasErrors(['type', 'account_id', 'category_id', 'amount', 'frequency', 'next_due_date']);
    }

    public function test_user_cannot_create_a_recurring_transaction_against_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUsersAccount = Account::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'expense',
            'account_id' => $otherUsersAccount->id,
            'category_id' => $category->id,
            'amount' => 45000,
            'frequency' => 'monthly',
            'next_due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['account_id']);
        $this->assertDatabaseCount('recurring_transactions', 0);
    }

    public function test_user_cannot_create_a_recurring_transaction_against_another_users_private_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherUser = User::factory()->create();
        $otherUsersCategory = Category::factory()->expense()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post('/planning/recurring', [
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $otherUsersCategory->id,
            'amount' => 45000,
            'frequency' => 'monthly',
            'next_due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['category_id']);
        $this->assertDatabaseCount('recurring_transactions', 0);
    }

    public function test_user_can_update_their_recurring_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $recurring = RecurringTransaction::factory()->for($user)->for($account)->for($category)->create(['description' => 'Old']);

        $response = $this->actingAs($user)->patch("/planning/recurring/{$recurring->id}", [
            'type' => $recurring->type->value,
            'account_id' => $recurring->account_id,
            'category_id' => $recurring->category_id,
            'amount' => (string) $recurring->amount,
            'description' => 'New description',
            'frequency' => $recurring->frequency->value,
            'next_due_date' => $recurring->next_due_date->toDateString(),
            'end_date' => null,
        ]);

        $response->assertRedirect();
        $this->assertSame('New description', $recurring->fresh()->description);
    }

    public function test_user_cannot_update_another_users_recurring_transaction(): void
    {
        $owner = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/planning/recurring/{$recurring->id}", [
            'type' => $recurring->type->value,
            'account_id' => $recurring->account_id,
            'category_id' => $recurring->category_id,
            'amount' => (string) $recurring->amount,
            'frequency' => $recurring->frequency->value,
            'next_due_date' => $recurring->next_due_date->toDateString(),
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_toggle_active_state(): void
    {
        $user = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($user)->create(['is_active' => true]);

        $response = $this->actingAs($user)->patch("/planning/recurring/{$recurring->id}/toggle");

        $response->assertRedirect();
        $this->assertFalse($recurring->fresh()->is_active);
    }

    public function test_user_cannot_toggle_another_users_recurring_transaction(): void
    {
        $owner = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($owner)->create(['is_active' => true]);
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/planning/recurring/{$recurring->id}/toggle");

        $response->assertNotFound();
        $this->assertTrue($recurring->fresh()->is_active);
    }

    public function test_user_can_delete_their_recurring_transaction(): void
    {
        $user = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/planning/recurring/{$recurring->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('recurring_transactions', ['id' => $recurring->id]);
    }

    public function test_user_cannot_delete_another_users_recurring_transaction(): void
    {
        $owner = User::factory()->create();
        $recurring = RecurringTransaction::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->delete("/planning/recurring/{$recurring->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('recurring_transactions', ['id' => $recurring->id]);
    }

    public function test_a_template_on_a_deleted_account_is_skipped_without_stopping_other_users_templates(): void
    {
        Carbon::setTestNow('2026-10-12 06:00:00');
        $owner = User::factory()->create();
        $deletedAccount = Account::factory()->for($owner)->create();
        $broken = RecurringTransaction::factory()->for($owner)->for($deletedAccount)->create([
            'category_id' => Category::factory()->expense()->create(['user_id' => $owner->id])->id,
            'next_due_date' => '2026-10-10',
        ]);
        $deletedAccount->delete();

        $other = User::factory()->create();
        $otherAccount = Account::factory()->for($other)->create();
        RecurringTransaction::factory()->for($other)->for($otherAccount)->create([
            'category_id' => Category::factory()->expense()->create(['user_id' => $other->id])->id,
            'next_due_date' => '2026-10-12',
        ]);
        Log::spy();

        $generated = app(RecurringTransactionService::class)->generateDue();

        $this->assertSame(1, $generated);
        $this->assertSame(1, Transaction::forUser($other)->count());
        $this->assertSame(0, Transaction::withTrashed()->forUser($owner)->count());
        $this->assertSame('2026-10-10', $broken->fresh()->next_due_date->toDateString());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_templates_on_an_archived_account_or_a_deleted_category_are_skipped_and_logged(): void
    {
        Carbon::setTestNow('2026-10-12 06:00:00');
        $user = User::factory()->create();
        $archived = Account::factory()->for($user)->create(['is_active' => false]);
        $active = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $trashedCategory = Category::factory()->expense()->create(['user_id' => $user->id]);
        $onArchived = RecurringTransaction::factory()->for($user)->for($archived)->for($category)->create(['next_due_date' => '2026-10-12']);
        $onTrashedCategory = RecurringTransaction::factory()->for($user)->for($active)->for($trashedCategory)->create(['next_due_date' => '2026-10-12']);
        $trashedCategory->delete();
        Log::spy();

        $generated = app(RecurringTransactionService::class)->generateDue();

        $this->assertSame(0, $generated);
        $this->assertSame(0, Transaction::withTrashed()->forUser($user)->count());
        $this->assertSame('2026-10-12', $onArchived->fresh()->next_due_date->toDateString());
        $this->assertSame('2026-10-12', $onTrashedCategory->fresh()->next_due_date->toDateString());
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_a_template_that_fails_to_generate_is_reported_and_the_batch_continues(): void
    {
        Carbon::setTestNow('2026-10-12 06:00:00');
        Exceptions::fake();
        $failing = RecurringTransaction::factory()->create(['next_due_date' => '2026-10-12']);
        $working = RecurringTransaction::factory()->create(['next_due_date' => '2026-10-12']);
        $recorded = [];
        $this->app->instance(RecordsTransactions::class, new class($failing->id, $recorded) implements RecordsTransactions
        {
            public function __construct(private int $failingId, private array &$recorded) {}

            public function record(User $user, TransactionType $type, array $data): int
            {
                return 0;
            }

            public function recordRecurring(User $user, TransactionType $type, array $data, int $recurringTransactionId): void
            {
                if ($recurringTransactionId === $this->failingId) {
                    throw new RuntimeException('boom');
                }

                $this->recorded[] = $recurringTransactionId;
            }

            public function forget(User $user, int $transactionId): void {}
        });

        $generated = app(RecurringTransactionService::class)->generateDue();

        $this->assertSame(1, $generated);
        $this->assertSame([$working->id], $recorded);
        $this->assertSame('2026-10-12', $failing->fresh()->next_due_date->toDateString());
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_a_template_another_run_already_generated_is_not_generated_again(): void
    {
        Carbon::setTestNow('2026-10-12 06:00:00');
        $template = RecurringTransaction::factory()->create(['next_due_date' => '2026-10-12', 'frequency' => 'monthly']);
        $advanced = false;
        // A second run moves the template on right after this run has read it.
        RecurringTransaction::retrieved(function () use ($template, &$advanced) {
            if (! $advanced) {
                $advanced = true;
                DB::table('recurring_transactions')->where('id', $template->id)->update(['next_due_date' => '2026-11-12']);
            }
        });

        $this->assertSame(0, app(RecurringTransactionService::class)->generateDue());
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertSame('2026-11-12', $template->fresh()->next_due_date->toDateString());
        $this->assertInstanceOf(ShouldBeUnique::class, new GenerateDueRecurringTransactions);
    }

    public function test_upcoming_payment_alerts_skip_templates_on_a_deleted_account(): void
    {
        Carbon::setTestNow('2026-10-12 07:00:00');
        $owner = User::factory()->create();
        $deletedAccount = Account::factory()->for($owner)->create();
        RecurringTransaction::factory()->for($owner)->for($deletedAccount)->create([
            'category_id' => Category::factory()->expense()->create(['user_id' => $owner->id])->id,
            'next_due_date' => '2026-10-13',
        ]);
        $deletedAccount->delete();
        $other = User::factory()->create();
        RecurringTransaction::factory()->for($other)->for(Account::factory()->for($other))->create([
            'category_id' => Category::factory()->expense()->create(['user_id' => $other->id])->id,
            'next_due_date' => '2026-10-13',
        ]);

        $this->artisan('app:notify-upcoming-recurring-payments')->assertSuccessful();

        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(1, $other->notifications()->where('type', UpcomingRecurringPayment::class)->count());
    }
}
