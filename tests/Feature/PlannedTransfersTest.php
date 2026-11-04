<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\SeriesPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlannedTransfersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $checking;

    private Account $savings;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->user = User::factory()->create(['timezone' => 'UTC']);
        $this->checking = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 5000]);
        $this->savings = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $this->actingAs($this->user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'transfer', 'description' => 'To savings', 'amount' => 1000, 'date' => '2026-11-20', 'certainty' => 'confirmed',
            'account_id' => $this->checking->id, 'counter_account_id' => $this->savings->id,
        ], $overrides);
    }

    private function transfer(): PlannedEvent
    {
        $this->post('/planning/cash-flow', $this->payload())->assertSessionHasNoErrors();

        return PlannedEvent::sole();
    }

    public function test_a_transfer_is_planned_between_two_accounts_without_a_category(): void
    {
        $plan = $this->transfer();

        $this->assertSame(TransactionType::TransferOut, $plan->type);
        $this->assertSame($this->checking->id, $plan->account_id);
        $this->assertSame($this->savings->id, $plan->counter_account_id);
        $this->assertNull($plan->category_id);
    }

    public function test_a_planned_transfer_needs_two_different_accounts_in_one_currency(): void
    {
        $dollars = Account::factory()->for($this->user)->create(['currency' => 'USD']);
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $this->post('/planning/cash-flow', $this->payload(['counter_account_id' => null]))->assertSessionHasErrors('counter_account_id');
        $this->post('/planning/cash-flow', $this->payload(['counter_account_id' => $this->checking->id]))->assertSessionHasErrors('counter_account_id');
        $this->post('/planning/cash-flow', $this->payload(['counter_account_id' => $dollars->id]))->assertSessionHasErrors('counter_account_id');
        $this->post('/planning/cash-flow', $this->payload(['category_id' => $category->id]))->assertSessionHasErrors('category_id');
        $this->post('/planning/cash-flow', $this->payload(['account_id' => null]))->assertSessionHasErrors('account_id');

        $this->assertSame(0, PlannedEvent::count());
    }

    public function test_confirming_records_both_legs_and_links_the_outgoing_one(): void
    {
        $plan = $this->transfer();

        $this->post("/planning/cash-flow/{$plan->id}/realize")->assertSessionHasNoErrors()->assertSessionHas('success');

        $legs = Transaction::orderBy('id')->get();
        $this->assertCount(2, $legs);
        $this->assertSame(TransactionType::TransferOut, $legs[0]->type);
        $this->assertSame($this->checking->id, $legs[0]->account_id);
        $this->assertSame($this->savings->id, $legs[1]->account_id);
        $this->assertSame('2026-11-10', $legs[0]->date->toDateString(), 'a later plan is confirmed today');
        $this->assertSame($legs[0]->id, $plan->fresh()->realized_transaction_id);
    }

    public function test_deleting_or_restoring_either_leg_reopens_or_closes_the_plan(): void
    {
        $plan = $this->transfer();
        $this->post("/planning/cash-flow/{$plan->id}/realize");
        [$out, $in] = Transaction::orderBy('id')->get();

        $this->delete("/finances/transactions/{$in->id}")->assertSessionHasNoErrors();
        $this->assertSame(PlannedEventStatus::Planned, $plan->fresh()->status);

        $this->post("/settings/trash/transactions/{$in->id}/restore")->assertSessionHasNoErrors();
        $this->assertSame(PlannedEventStatus::Realized, $plan->fresh()->status);

        $this->delete("/finances/transactions/{$out->id}");
        $this->assertSame(PlannedEventStatus::Planned, $plan->fresh()->status);
    }

    public function test_a_planned_transfer_is_net_zero_in_the_projection_but_moves_interest(): void
    {
        $this->transfer();
        $this->savings->update(['yield_rate' => 12, 'yield_compounding' => 'monthly']);

        $months = app(CashFlowProjector::class)->project($this->user, ForecastView::Conservative, 3)['COP']['months'];

        $this->assertSame('0.00', $months[0]['income']->toDecimalString());
        $this->assertSame('0.00', $months[0]['expense']->toDecimalString());
        $this->assertTrue($months[1]['interest']->isPositive(), 'the transferred money earns in the savings account');
    }

    public function test_an_auto_posting_transfer_series_records_both_legs_and_is_blocked_when_the_destination_is_archived(): void
    {
        $series = PlannedEventSeries::factory()->for($this->user)->create([
            'type' => 'transfer_out', 'currency' => 'COP', 'amount' => 200, 'account_id' => $this->checking->id,
            'counter_account_id' => $this->savings->id, 'starts_on' => '2026-11-10', 'auto_post' => true,
        ]);
        $this->post("/planning/series/{$series->id}/pause");
        $this->post("/planning/series/{$series->id}/resume");
        auth()->logout();

        $this->assertSame($this->savings->id, $series->events()->orderBy('date')->first()->counter_account_id);
        $this->assertSame(1, app(SeriesPoster::class)->postDue());
        $this->assertSame(2, Transaction::withoutGlobalScopes()->count());
        $this->assertTrue(Transaction::withoutGlobalScopes()->get()->every(fn (Transaction $leg) => $leg->is_recurring_generated));

        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->savings->update(['is_active' => false]);
        $this->assertSame(0, app(SeriesPoster::class)->postDue());
        $this->assertSame('account_inactive', $series->events()->where('date', '2026-12-10')->sole()->post_error);
    }
}
