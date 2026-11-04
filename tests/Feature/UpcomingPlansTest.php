<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Contracts\UpcomingPlans;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Services\UpcomingPlansService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UpcomingPlansTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->user = User::factory()->create(['timezone' => 'UTC']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
    }

    private function plan(string $date, array $attributes = []): PlannedEvent
    {
        return PlannedEvent::factory()->for($this->user)->expense()->create($attributes + ['date' => $date, 'currency' => 'COP', 'amount' => 100]);
    }

    private function plans(): UpcomingPlans
    {
        return app(UpcomingPlans::class);
    }

    public function test_the_contract_resolves_to_its_implementation(): void
    {
        $this->assertInstanceOf(UpcomingPlansService::class, $this->plans());
    }

    public function test_bands_split_open_plans_into_overdue_and_the_next_days_and_cap_the_rows(): void
    {
        $overdue = $this->plan('2026-11-08');
        $this->plan('2026-11-10');
        $this->plan('2026-12-10');
        $this->plan('2026-12-11');
        $this->plan('2026-11-09', ['status' => PlannedEventStatus::Realized]);
        PlannedEvent::factory()->expense()->create(['date' => '2026-11-11']);
        foreach (range(1, 11) as $day) {
            $this->plan('2026-11-2'.min($day, 9));
        }
        $match = Transaction::factory()->for($this->user)->for($this->account)->create(['type' => 'expense', 'amount' => 100, 'date' => '2026-11-09']);

        $bands = $this->plans()->listBands($this->user, 30);

        $this->assertSame(1, $bands['overdue']['count']);
        $this->assertSame($overdue->id, $bands['overdue']['items'][0]['id']);
        $this->assertSame($match->id, $bands['overdue']['items'][0]['suggestedMatch']['id']);
        $this->assertSame(13, $bands['upcoming']['count']);
        $this->assertCount(UpcomingPlans::BAND_LIMIT, $bands['upcoming']['items']);
        $this->assertSame('2026-11-10', $bands['upcoming']['items'][0]['date']);
    }

    public function test_bands_follow_the_account_category_and_type_filters(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $onAccount = $this->plan('2026-11-12', ['account_id' => $this->account->id]);
        $inCategory = $this->plan('2026-11-12', ['category_id' => $category->id]);
        $income = PlannedEvent::factory()->for($this->user)->income()->create(['date' => '2026-11-12', 'currency' => 'COP']);

        $ids = fn (array $filters) => array_column($this->plans()->listBands($this->user, 30, $filters)['upcoming']['items'], 'id');

        $this->assertSame([$onAccount->id], $ids(['account' => $this->account->id]));
        $this->assertSame([$inCategory->id], $ids(['category' => $category->id]));
        $this->assertSame([$income->id], $ids(['type' => 'income']));
    }

    public function test_provenance_says_which_transactions_came_from_a_plan(): void
    {
        $series = PlannedEventSeries::factory()->for($this->user)->create();
        $fromSeries = Transaction::factory()->for($this->user)->for($this->account)->create();
        $fromPlan = Transaction::factory()->for($this->user)->for($this->account)->create();
        $plain = Transaction::factory()->for($this->user)->for($this->account)->create();
        $occurrence = $this->plan('2026-11-01', ['series_id' => $series->id, 'status' => PlannedEventStatus::Realized, 'realized_transaction_id' => $fromSeries->id]);
        $oneOff = $this->plan('2026-11-02', ['status' => PlannedEventStatus::Realized, 'realized_transaction_id' => $fromPlan->id]);

        $this->assertSame([
            $fromSeries->id => ['plannedEventId' => $occurrence->id, 'seriesId' => $series->id],
            $fromPlan->id => ['plannedEventId' => $oneOff->id, 'seriesId' => null],
        ], $this->plans()->provenance($this->user, [$fromSeries->id, $fromPlan->id, $plain->id]));
    }

    public function test_needs_review_lists_overdue_and_blocked_plans_of_the_owner_only(): void
    {
        $overdue = $this->plan('2026-11-01');
        $blocked = $this->plan('2026-11-10', ['auto_post' => true, 'post_error' => 'account_inactive']);
        $this->plan('2026-11-10');
        $this->plan('2026-11-02', ['status' => PlannedEventStatus::Skipped]);
        PlannedEvent::factory()->expense()->create(['date' => '2026-11-01']);

        $review = $this->plans()->needsReview($this->user, 1);

        $this->assertSame(2, $review['count']);
        $this->assertSame([$overdue->id], array_column($review['items'], 'id'));
        $this->assertSame(2, $this->plans()->needsReviewCount($this->user));
        $this->assertSame([$overdue->id, $blocked->id], array_column($this->plans()->needsReview($this->user, 5)['items'], 'id'));
    }
}
