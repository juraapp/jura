<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlannedEventsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'income',
            'description' => 'Freelance project',
            'amount' => 1200000,
            'date' => Carbon::now()->addDays(10)->toDateString(),
            'certainty' => 'expected',
        ], $overrides);
    }

    public function test_index_lists_open_and_overdue_events_but_not_old_closed_ones_or_other_users(): void
    {
        $user = User::factory()->create();
        PlannedEvent::factory()->for($user)->create(['date' => Carbon::now()->addDays(10)]);
        PlannedEvent::factory()->for($user)->create(['date' => Carbon::now()->subDay()]);
        PlannedEvent::factory()->for($user)->status(PlannedEventStatus::Realized)->create(['date' => Carbon::now()->subDays(40)]);
        PlannedEvent::factory()->create(['date' => Carbon::now()->addDays(3)]);

        $this->actingAs($user)->get('/planning/cash-flow')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CashFlow/Index')
                ->has('events', 2)
                ->has('projection')
                ->where('view', 'expected')
            );
    }

    public function test_user_can_plan_a_single_event(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency' => 'USD']);
        $category = Category::factory()->income()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/planning/cash-flow', $this->payload([
                'account_id' => $account->id,
                'category_id' => $category->id,
                'certainty' => 'confirmed',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $event = PlannedEvent::firstOrFail();
        $this->assertSame(TransactionType::Income, $event->type);
        $this->assertSame('1200000.00', $event->amount->toDecimalString());
        $this->assertSame('USD', $event->currency);
        $this->assertSame(Certainty::Confirmed, $event->certainty);
        $this->assertSame(PlannedEventStatus::Planned, $event->status);
        $this->assertNull($event->series_id);
    }

    public function test_currency_defaults_to_the_users_default_when_no_account_is_chosen(): void
    {
        $user = User::factory()->create(['currency_default' => 'EUR']);

        $this->actingAs($user)->post('/planning/cash-flow', $this->payload())->assertRedirect();

        $this->assertSame('EUR', PlannedEvent::firstOrFail()->currency);
    }

    public function test_planning_an_event_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $foreignAccount = Account::factory()->create();

        $this->actingAs($user)
            ->post('/planning/cash-flow', $this->payload([
                'type' => 'transfer_out',
                'amount' => 0,
                'date' => '',
                'certainty' => 'maybe',
                'account_id' => $foreignAccount->id,
            ]))
            ->assertSessionHasErrors(['type', 'amount', 'date', 'certainty', 'account_id']);

        $this->assertDatabaseCount('planned_events', 0);
    }

    public function test_a_repeat_rule_creates_a_series_with_all_occurrences(): void
    {
        $user = User::factory()->create();
        $start = Carbon::now()->addDays(5);

        $this->actingAs($user)
            ->post('/planning/cash-flow', $this->payload([
                'date' => $start->toDateString(),
                'frequency' => 'monthly',
                'ends_on' => $start->copy()->addMonthsNoOverflow(3)->toDateString(),
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('planned_event_series', 1);
        $this->assertSame(4, PlannedEvent::count());
        $this->assertSame(1, PlannedEvent::whereNotNull('series_id')->distinct('series_id')->count('series_id'));
        $this->assertSame($start->toDateString(), PlannedEvent::orderBy('date')->first()->date->toDateString());
    }

    public function test_an_open_ended_series_is_materialized_twelve_months_ahead(): void
    {
        $user = User::factory()->create();
        $start = Carbon::now()->addDay();

        $this->actingAs($user)->post('/planning/cash-flow', $this->payload([
            'date' => $start->toDateString(),
            'frequency' => 'monthly',
        ]))->assertRedirect();

        $last = PlannedEvent::orderByDesc('date')->first();
        $this->assertTrue($last->date->lte(Carbon::now()->addMonthsNoOverflow(12)));
        $this->assertGreaterThanOrEqual(12, PlannedEvent::count());
        $this->assertLessThanOrEqual(13, PlannedEvent::count());
    }

    public function test_other_users_events_cannot_be_changed_or_reached(): void
    {
        $user = User::factory()->create();
        $foreign = PlannedEvent::factory()->create();

        $this->actingAs($user);

        $this->patch("/planning/cash-flow/{$foreign->id}", $this->payload(['scope' => 'this']))->assertNotFound();
        $this->delete("/planning/cash-flow/{$foreign->id}")->assertNotFound();
        $this->post("/planning/cash-flow/{$foreign->id}/skip")->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/planning/cash-flow')->assertRedirect('/login');
    }

    /**
     * @return array{0: User, 1: Collection<int, PlannedEvent>}
     */
    private function userWithMonthlySeries(): array
    {
        $user = User::factory()->create();
        $start = Carbon::now()->addDays(5);

        $this->actingAs($user)->post('/planning/cash-flow', $this->payload([
            'date' => $start->toDateString(),
            'frequency' => 'monthly',
            'ends_on' => $start->copy()->addMonthsNoOverflow(3)->toDateString(),
        ]))->assertRedirect();

        return [$user, PlannedEvent::orderBy('date')->get()];
    }

    public function test_updating_one_occurrence_leaves_the_rest_of_the_series_alone(): void
    {
        [$user, $events] = $this->userWithMonthlySeries();

        $this->actingAs($user)
            ->patch("/planning/cash-flow/{$events[1]->id}", $this->payload(['amount' => 999, 'scope' => 'this']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = PlannedEvent::orderBy('date')->get();
        $this->assertSame('1200000.00', $fresh[0]->amount->toDecimalString());
        $this->assertSame('999.00', $fresh[1]->amount->toDecimalString());
        $this->assertSame('1200000.00', $fresh[2]->amount->toDecimalString());
    }

    public function test_updating_with_future_scope_changes_this_and_later_open_occurrences_only(): void
    {
        [$user, $events] = $this->userWithMonthlySeries();
        $events[2]->update(['status' => PlannedEventStatus::Realized]);

        $this->actingAs($user)
            ->patch("/planning/cash-flow/{$events[1]->id}", $this->payload([
                'amount' => 777,
                'description' => 'Raise',
                'date' => Carbon::now()->addYear()->toDateString(),
                'scope' => 'future',
            ]))
            ->assertRedirect();

        $fresh = PlannedEvent::orderBy('date')->get();
        $this->assertSame('1200000.00', $fresh[0]->amount->toDecimalString());
        $this->assertSame('777.00', $fresh[1]->amount->toDecimalString());
        $this->assertSame('Raise', $fresh[1]->description);
        $this->assertSame('1200000.00', $fresh[2]->amount->toDecimalString(), 'closed occurrences are untouched');
        $this->assertSame('777.00', $fresh[3]->amount->toDecimalString());
        $this->assertSame($events[1]->date->toDateString(), $fresh[1]->date->toDateString(), 'future scope never moves dates');
        $this->assertSame('777.00', PlannedEventSeries::firstOrFail()->amount->toDecimalString());
    }

    public function test_deleting_one_series_occurrence_skips_it_and_keeps_the_series(): void
    {
        [$user, $events] = $this->userWithMonthlySeries();

        $this->actingAs($user)
            ->delete("/planning/cash-flow/{$events[1]->id}", ['scope' => 'this'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(4, PlannedEvent::count());
        $this->assertSame(PlannedEventStatus::Skipped, $events[1]->fresh()->status);
    }

    public function test_deleting_future_occurrences_trims_the_series(): void
    {
        [$user, $events] = $this->userWithMonthlySeries();

        $this->actingAs($user)
            ->delete("/planning/cash-flow/{$events[1]->id}", ['scope' => 'future'])
            ->assertRedirect();

        $this->assertSame(1, PlannedEvent::count());
        $this->assertSame(
            $events[1]->date->copy()->subDay()->toDateString(),
            PlannedEventSeries::firstOrFail()->ends_on->toDateString(),
        );
    }

    public function test_deleting_a_standalone_event_removes_it(): void
    {
        $user = User::factory()->create();
        $event = PlannedEvent::factory()->for($user)->create();

        $this->actingAs($user)->delete("/planning/cash-flow/{$event->id}")->assertRedirect();

        $this->assertDatabaseMissing('planned_events', ['id' => $event->id]);
    }

    public function test_realizing_records_the_transaction_and_closes_the_plan(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->income()->create(['user_id' => $user->id]);
        $event = PlannedEvent::factory()->for($user)->income()->create(['amount' => 1200000, 'description' => 'Client A']);

        $this->actingAs($user)
            ->post("/planning/cash-flow/{$event->id}/realize", [
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $transaction = Transaction::firstOrFail();
        $this->assertSame(TransactionType::Income, $transaction->type);
        $this->assertSame('1200000.00', $transaction->amount->toDecimalString());
        $this->assertSame(Carbon::today()->toDateString(), $transaction->date->toDateString());
        $this->assertSame('Client A', $transaction->description);

        $event->refresh();
        $this->assertSame(PlannedEventStatus::Realized, $event->status);
        $this->assertSame($transaction->id, $event->realized_transaction_id);
    }

    public function test_realizing_can_override_the_amount_and_date(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $event = PlannedEvent::factory()->for($user)->expense()->create(['amount' => 100]);

        $this->actingAs($user)->post("/planning/cash-flow/{$event->id}/realize", [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 87.5,
            'date' => '2026-01-15',
        ])->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->assertSame('87.50', $transaction->amount->toDecimalString());
        $this->assertSame('2026-01-15', $transaction->date->toDateString());
    }

    public function test_realizing_requires_an_account_and_a_category(): void
    {
        $user = User::factory()->create();
        $event = PlannedEvent::factory()->for($user)->create();

        $this->actingAs($user)
            ->post("/planning/cash-flow/{$event->id}/realize", [])
            ->assertSessionHasErrors(['account_id', 'category_id']);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_a_closed_event_cannot_be_realized_twice(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $event = PlannedEvent::factory()->for($user)->status(PlannedEventStatus::Skipped)->create();

        $this->actingAs($user)->post("/planning/cash-flow/{$event->id}/realize", [
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_skipping_marks_the_event_as_skipped(): void
    {
        $user = User::factory()->create();
        $event = PlannedEvent::factory()->for($user)->create();

        $this->actingAs($user)->post("/planning/cash-flow/{$event->id}/skip")->assertRedirect()->assertSessionHas('success');

        $this->assertSame(PlannedEventStatus::Skipped, $event->fresh()->status);
    }

    public function test_index_suggests_a_similar_real_transaction_as_a_match(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['currency' => 'COP']);
        $match = Transaction::factory()->for($user)->for($account)->create([
            'type' => 'expense', 'amount' => 101000, 'date' => Carbon::now()->subDay(),
        ]);
        Transaction::factory()->for($user)->for($account)->create([
            'type' => 'expense', 'amount' => 5000, 'date' => Carbon::now()->subDay(),
        ]);
        PlannedEvent::factory()->for($user)->expense()->create([
            'amount' => 100000, 'currency' => 'COP', 'date' => Carbon::now()->addDays(2),
        ]);

        $this->actingAs($user)->get('/planning/cash-flow')->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.suggestedMatch.id', $match->id)
        );
    }

    public function test_linking_connects_an_existing_transaction_without_creating_a_new_one(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create(['type' => 'expense']);
        $event = PlannedEvent::factory()->for($user)->expense()->create();

        $this->actingAs($user)
            ->post("/planning/cash-flow/{$event->id}/link", ['transaction_id' => $transaction->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $event->refresh();
        $this->assertSame(PlannedEventStatus::Realized, $event->status);
        $this->assertSame($transaction->id, $event->realized_transaction_id);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_linking_rejects_foreign_wrong_type_or_already_linked_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $income = Transaction::factory()->for($user)->for($account)->income()->create();
        $taken = Transaction::factory()->for($user)->for($account)->create(['type' => 'expense']);
        PlannedEvent::factory()->for($user)->expense()->status(PlannedEventStatus::Realized)->create(['realized_transaction_id' => $taken->id]);
        $foreign = Transaction::factory()->create(['type' => 'expense']);
        $event = PlannedEvent::factory()->for($user)->expense()->create();

        $this->actingAs($user);

        foreach ([$income->id, $taken->id, $foreign->id] as $transactionId) {
            $this->post("/planning/cash-flow/{$event->id}/link", ['transaction_id' => $transactionId])
                ->assertSessionHasErrors('transaction_id');
        }

        $this->assertSame(PlannedEventStatus::Planned, $event->fresh()->status);
    }

    public function test_the_extend_command_tops_up_open_ended_series_without_duplicates(): void
    {
        $user = User::factory()->create();
        $series = PlannedEventSeries::factory()->for($user)->create([
            'starts_on' => Carbon::now()->toDateString(),
            'ends_on' => null,
        ]);

        $this->artisan('planning:extend-series')->assertSuccessful();
        $first = $series->events()->count();

        $this->artisan('planning:extend-series')->assertSuccessful();

        $this->assertGreaterThanOrEqual(12, $first);
        $this->assertSame($first, $series->events()->count());
    }
}
