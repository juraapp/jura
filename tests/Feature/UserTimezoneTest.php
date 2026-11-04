<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\RecurringTransaction;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\RecurringTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Today" on the server is the user's date in their timezone (Preferences), not the UTC date.
 */
class UserTimezoneTest extends TestCase
{
    use RefreshDatabase;

    /** 21:30 on 12 October in Bogotá (UTC-5), already the 13th in UTC. */
    private const EVENING_IN_BOGOTA = '2026-10-13 02:30:00';

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::EVENING_IN_BOGOTA);
        $this->user = User::factory()->create(['timezone' => 'America/Bogota', 'currency_default' => 'COP']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 100000]);
    }

    public function test_today_is_the_users_calendar_date_and_falls_back_to_the_app_timezone(): void
    {
        $this->assertSame('2026-10-12', $this->user->today()->toDateString());
        $this->assertSame('2026-10-13', User::factory()->make(['timezone' => 'UTC'])->today()->toDateString());
        $this->assertSame('2026-10-13', User::factory()->make(['timezone' => 'Not/AZone'])->today()->toDateString());
        $this->assertSame('2026-10-12', User::todayFor($this->user->id)->toDateString());
    }

    public function test_today_for_a_user_id_looks_the_user_up_once(): void
    {
        DB::enableQueryLog();

        User::todayFor($this->user->id);
        User::todayFor($this->user->id);

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_pages_share_today_in_the_users_timezone(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('today', '2026-10-12'));
    }

    public function test_realizing_a_later_plan_and_buying_an_item_default_to_the_users_date(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $event = PlannedEvent::factory()->for($this->user)->create(['type' => 'expense', 'amount' => 100, 'currency' => 'COP', 'date' => '2026-10-20']);
        $item = WishlistItem::factory()->for($this->user)->create(['currency' => 'COP', 'bucket_id' => null]);

        $this->actingAs($this->user)
            ->post("/planning/cash-flow/{$event->id}/realize", ['account_id' => $this->account->id, 'category_id' => $category->id])
            ->assertSessionHasNoErrors();
        $this->post("/planning/wishlist/{$item->id}/purchase", ['account_id' => $this->account->id, 'category_id' => $category->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-12', '2026-10-12'], Transaction::orderBy('id')->get()->map(fn ($transaction) => $transaction->date->toDateString())->all());
        $this->assertSame('2026-10-12', $item->fresh()->purchased_on->toDateString());
    }

    public function test_reports_and_the_projection_start_in_the_users_current_month(): void
    {
        Carbon::setTestNow('2026-11-01 02:00:00');

        $this->actingAs($this->user)->get('/reports/monthly')
            ->assertInertia(fn (Assert $page) => $page->where('year', 2026)->where('month', 10));

        $projection = app(CashFlowProjector::class)->project($this->user, ForecastView::Expected, 3);
        $this->assertSame('2026-10', $projection->first()['months'][0]['month']);
    }

    public function test_a_recurring_template_is_generated_only_once_its_date_has_come_for_its_owner(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $template = RecurringTransaction::factory()->for($this->user)->for($this->account)->for($category)
            ->create(['next_due_date' => '2026-10-13', 'type' => 'expense']);

        $this->assertSame(0, app(RecurringTransactionService::class)->generateDue());

        Carbon::setTestNow('2026-10-13 05:30:00');
        $this->assertSame(1, app(RecurringTransactionService::class)->generateDue());
        $this->assertSame('2026-10-13', Transaction::firstOrFail()->date->toDateString());
        $this->assertNotSame('2026-10-13', $template->fresh()->next_due_date->toDateString());
    }

    public function test_a_bucket_cycle_closes_after_it_has_ended_in_the_owners_timezone(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create([
            'currency' => 'COP', 'cycle' => 'monthly', 'carry_over' => CarryOverPolicy::Carry, 'tracking_started_on' => '2026-10-01',
        ]);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => 300, 'currency' => 'COP', 'date' => '2026-10-10']);

        Carbon::setTestNow('2026-11-01 03:00:00');
        $this->artisan('planning:close-bucket-cycles')->assertSuccessful();
        $this->assertNull($bucket->fresh()->cycle_closed_through);

        Carbon::setTestNow('2026-11-01 05:30:00');
        $this->artisan('planning:close-bucket-cycles')->assertSuccessful();
        $this->assertSame('2026-10-31', $bucket->fresh()->cycle_closed_through->toDateString());
    }
}
