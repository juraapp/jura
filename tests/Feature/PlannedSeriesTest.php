<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlannedSeriesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->user = User::factory()->create(['timezone' => 'UTC']);
    }

    /**
     * A monthly expense on the 5th, started in September, with an overdue open October occurrence,
     * a realized September one and open ones from December on.
     */
    private function series(array $attributes = []): PlannedEventSeries
    {
        $series = PlannedEventSeries::factory()->for($this->user)->create($attributes + [
            'type' => 'expense',
            'description' => 'Gym',
            'amount' => 100,
            'currency' => 'COP',
            'starts_on' => '2026-09-05',
            'frequency' => RecurringFrequency::Monthly,
        ]);

        $make = fn (string $date, PlannedEventStatus $status = PlannedEventStatus::Planned) => PlannedEvent::factory()->for($this->user)->status($status)->create([
            'series_id' => $series->id, 'type' => 'expense', 'description' => 'Gym', 'amount' => 100, 'currency' => 'COP', 'date' => $date,
            'auto_post' => (bool) $series->auto_post,
        ]);
        $make('2026-09-05', PlannedEventStatus::Realized);
        $make('2026-10-05');
        $make('2026-11-05');
        $make('2026-12-05');
        $make('2027-01-05');

        return $series;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Gym',
            'amount' => 100,
            'certainty' => 'confirmed',
            'frequency' => 'monthly',
            'next_date' => '2026-12-05',
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function openDates(PlannedEventSeries $series): array
    {
        return $series->events()->open()->orderBy('date')->get()
            ->mapWithKeys(fn (PlannedEvent $event) => [$event->date->toDateString() => $event->amount->toDecimalString()])->all();
    }

    public function test_a_plain_edit_changes_open_occurrences_from_today_on_and_keeps_overdue_ones(): void
    {
        $series = $this->series();

        $this->actingAs($this->user)
            ->patch("/planning/series/{$series->id}", $this->payload(['amount' => 120, 'description' => 'Gym plus', 'notes' => 'Raised']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $open = $this->openDates($series);
        $this->assertSame('100.00', $open['2026-10-05'], 'overdue occurrences keep their amount');
        $this->assertSame('100.00', $open['2026-11-05'], 'an occurrence earlier today-month but before today is overdue');
        $this->assertSame('120.00', $open['2026-12-05']);
        $this->assertSame('120.00', $open['2027-01-05']);
        $this->assertSame('Raised', PlannedEvent::where('date', '2027-01-05')->value('notes'));
        $this->assertSame('120.00', $series->fresh()->amount->toDecimalString());
        $this->assertSame('2026-09-05', $series->fresh()->starts_on->toDateString(), 'the rhythm anchor is unchanged');
    }

    public function test_a_new_frequency_or_next_date_rebuilds_upcoming_occurrences_and_keeps_overdue_and_realized_ones(): void
    {
        $series = $this->series(['excluded_dates' => ['2026-08-05', '2027-02-05']]);

        $this->actingAs($this->user)
            ->patch("/planning/series/{$series->id}", $this->payload(['next_date' => '2026-11-20']))
            ->assertSessionHasNoErrors();

        $series->refresh();
        $this->assertSame('2026-11-20', $series->starts_on->toDateString());
        $this->assertSame(['2026-08-05'], $series->excluded_dates, 'only past exclusions are kept');
        $this->assertSame(1, $series->events()->where('status', 'realized')->count());

        $dates = array_keys($this->openDates($series));
        $this->assertSame(['2026-10-05', '2026-11-05', '2026-11-20', '2026-12-20'], array_slice($dates, 0, 4));
        $this->assertNotContains('2026-12-05', $dates);
        $this->assertSame('2027-10-20', end($dates), 'twelve months from today');

        $this->actingAs($this->user)
            ->patch("/planning/series/{$series->id}", $this->payload(['frequency' => 'weekly', 'next_date' => '2026-11-20']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-05', '2026-11-05', '2026-11-20', '2026-11-27', '2026-12-04'], array_slice(array_keys($this->openDates($series)), 0, 5));
    }

    public function test_the_next_date_cannot_be_in_the_past_and_the_end_must_follow_it(): void
    {
        $series = $this->series();

        $this->actingAs($this->user)
            ->patch("/planning/series/{$series->id}", $this->payload(['next_date' => '2026-11-09', 'ends_on' => '2026-11-01']))
            ->assertSessionHasErrors(['next_date', 'ends_on']);
    }

    public function test_an_earlier_end_removes_later_occurrences_and_a_later_one_creates_them(): void
    {
        $series = $this->series();

        $this->actingAs($this->user)->patch("/planning/series/{$series->id}", $this->payload(['ends_on' => '2026-12-31']))->assertSessionHasNoErrors();
        $this->assertSame(['2026-10-05', '2026-11-05', '2026-12-05'], array_keys($this->openDates($series)));

        $this->actingAs($this->user)->patch("/planning/series/{$series->id}", $this->payload(['ends_on' => '2027-03-31']))->assertSessionHasNoErrors();
        $this->assertSame(['2026-10-05', '2026-11-05', '2026-12-05', '2027-01-05', '2027-02-05', '2027-03-05'], array_keys($this->openDates($series)));
    }

    public function test_turning_auto_post_off_clears_it_on_every_open_occurrence(): void
    {
        $account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $series = $this->series(['auto_post' => true, 'account_id' => $account->id, 'category_id' => $category->id]);

        $this->actingAs($this->user)
            ->patch("/planning/series/{$series->id}", $this->payload(['auto_post' => true]))
            ->assertSessionHasErrors(['account_id', 'category_id']);

        $this->actingAs($this->user)
            ->patch("/planning/series/{$series->id}", $this->payload(['auto_post' => false, 'account_id' => $account->id, 'category_id' => $category->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $series->events()->open()->where('auto_post', true)->count());
        $this->assertFalse($series->fresh()->auto_post);
    }

    public function test_pausing_removes_upcoming_occurrences_and_stops_overdue_ones_from_auto_posting(): void
    {
        $series = $this->series(['auto_post' => true]);

        $this->actingAs($this->user)->post("/planning/series/{$series->id}/pause")->assertSessionHas('success');

        $this->assertFalse($series->fresh()->is_active);
        $this->assertSame(['2026-10-05', '2026-11-05'], array_keys($this->openDates($series)));
        $this->assertSame(0, $series->events()->open()->where('auto_post', true)->count());

        $this->artisan('planning:extend-series')->assertSuccessful();
        $this->assertCount(2, $this->openDates($series));
    }

    public function test_resuming_starts_again_from_today_and_never_back_posts(): void
    {
        $series = $this->series();
        $this->actingAs($this->user)->post("/planning/series/{$series->id}/pause");

        Carbon::setTestNow('2027-02-10 09:00:00');
        $this->actingAs($this->user)->post("/planning/series/{$series->id}/resume")->assertSessionHas('success');

        $dates = array_keys($this->openDates($series));
        $this->assertTrue($series->fresh()->is_active);
        $this->assertSame(['2026-10-05', '2026-11-05', '2027-03-05'], array_slice($dates, 0, 3));
        $this->assertSame(0, Transaction::count());
    }

    public function test_deleting_a_series_keeps_its_history(): void
    {
        $series = $this->series();
        $skipped = PlannedEvent::where('date', '2026-10-05')->first();
        $skipped->update(['status' => PlannedEventStatus::Skipped]);

        $this->actingAs($this->user)->delete("/planning/series/{$series->id}")->assertSessionHas('success');

        $this->assertDatabaseMissing('planned_event_series', ['id' => $series->id]);
        $this->assertSame(['realized', 'skipped'], PlannedEvent::orderBy('date')->pluck('status')->map->value->all());
        $this->assertSame(0, PlannedEvent::whereNotNull('series_id')->count());
    }

    public function test_another_users_series_is_out_of_reach(): void
    {
        $series = $this->series();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->patch("/planning/series/{$series->id}", $this->payload())->assertNotFound();
        $this->actingAs($intruder)->post("/planning/series/{$series->id}/pause")->assertNotFound();
        $this->actingAs($intruder)->post("/planning/series/{$series->id}/resume")->assertNotFound();
        $this->actingAs($intruder)->delete("/planning/series/{$series->id}")->assertNotFound();
        $this->assertTrue($series->fresh()->is_active);
    }

    public function test_the_form_gets_a_series_with_its_next_date(): void
    {
        $series = $this->series(['auto_post' => true, 'ends_on' => '2027-06-30']);

        $this->actingAs($this->user)->getJson("/planning/series/{$series->id}/edit-data")->assertOk()
            ->assertJsonPath('frequency', 'monthly')
            ->assertJsonPath('nextDate', '2026-12-05')
            ->assertJsonPath('endsOn', '2027-06-30')
            ->assertJsonPath('autoPost', true)
            ->assertJsonPath('isActive', true);

        $this->actingAs(User::factory()->create())->getJson("/planning/series/{$series->id}/edit-data")->assertNotFound();
    }

    public function test_the_cash_flow_page_lists_repeating_plans_with_their_next_date(): void
    {
        $series = $this->series(['auto_post' => true]);
        $paused = PlannedEventSeries::factory()->for($this->user)->create(['description' => 'Zoo', 'is_active' => false]);
        PlannedEventSeries::factory()->create();

        $this->actingAs($this->user)->get('/planning/cash-flow?section=repeating')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CashFlow/Index')
                ->where('section', 'repeating')
                ->has('series', 2)
                ->where('series.0.id', $series->id)
                ->where('series.0.nextDate', '2026-12-05')
                ->where('series.0.autoPost', true)
                ->where('series.1.id', $paused->id)
                ->where('series.1.isActive', false)
                ->missing('accounts')
            );
    }

    public function test_the_old_recurring_page_redirects_to_repeating_plans(): void
    {
        $this->actingAs($this->user)->get('/planning/recurring')->assertStatus(301)->assertRedirect('/planning/cash-flow?section=repeating');
    }
}
