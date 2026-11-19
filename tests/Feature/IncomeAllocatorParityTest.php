<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\BucketCycle;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\ForecastInputBuilder;
use App\Modules\Planning\Services\IncomeAllocator;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The forecast must split a planned income exactly like the real allocator splits the same income recorded today.
 * Each case lets the forecast predict one income, then records it for real and compares bucket by bucket; the real
 * split is also checked against hand-computed amounts, so both sides cannot be wrong the same way.
 */
class IncomeAllocatorParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $debit;

    private Account $cash;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 12:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->actingAs($this->user);
        $this->debit = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 5000]);
        $this->cash = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $this->food = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    }

    private function bucket(string $name, int $priority, array $attributes): Bucket
    {
        return Bucket::factory()->for($this->user)->create(array_merge([
            'name' => $name, 'currency' => 'COP', 'priority' => $priority, 'kind' => BucketKind::Savings, 'category_id' => null,
            'cycle' => null, 'cap_amount' => null, 'tracking_started_on' => '2026-10-01',
        ], $attributes));
    }

    private function fund(Bucket $bucket, int $amount, string $date, AllocationSource $source = AllocationSource::Adjustment): void
    {
        app(BucketLedger::class)->credit($bucket, Money::of($amount), $source, null, CarbonImmutable::parse($date));
    }

    /**
     * Predicts the split of an income recorded today, then records it and returns both, keyed by bucket name.
     *
     * @param  list<array{bucket_id: int, amount: string}>  $overrides
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function predictAndRecord(int $amount, Account $account, array $overrides = []): array
    {
        $plan = PlannedEvent::factory()->for($this->user)->income()->create([
            'amount' => $amount, 'currency' => 'COP', 'date' => '2026-11-10', 'account_id' => $account->id, 'certainty' => 'confirmed',
            'allocation_overrides' => $overrides ?: null,
        ]);

        $inputs = app(ForecastInputBuilder::class)->build($this->user, 'COP', ForecastView::Expected, CarbonImmutable::parse('2026-11-10'));
        $input = new EngineInput($inputs->start, $inputs->start, $inputs->startCash, $inputs->buckets, $inputs->flows);
        $result = (new ForecastEngine)->run($input, []);

        $names = Bucket::pluck('name', 'id');
        $predicted = [];
        foreach ($inputs->buckets as $bucket) {
            $after = $result->endBalances[$bucket->id];
            $before = $bucket->isFunded ? $bucket->envelope : Money::zero();
            $became = $bucket->isFunded || ($result->timeline[0]->envelopes()[$bucket->id] ?? null) !== null;
            $allocated = $became ? $after->subtract($before) : Money::zero();
            if (! $allocated->isZero()) {
                $predicted[$names[$bucket->id]] = $allocated->toDecimalString();
            }
        }

        $plan->delete();
        $record = fn () => app(RecordsTransactions::class)->record($this->user, TransactionType::Income, [
            'account_id' => $account->id, 'category_id' => null, 'amount' => $amount, 'date' => '2026-11-10', 'description' => 'Pay',
        ]);
        $transactionId = $overrides === []
            ? $record()
            : app(IncomeAllocator::class)->withOverrides($overrides, $record);

        $actual = Allocation::where('source_id', $transactionId)->whereIn('source', ['income', 'planned'])->get()
            ->groupBy('bucket_id')
            ->mapWithKeys(fn ($rows, $bucketId) => [$names[$bucketId] => Money::of($rows->sum(fn ($row) => $row->amount->toFloat()))->toDecimalString()])
            ->all();

        ksort($predicted);
        ksort($actual);

        return [$predicted, $actual];
    }

    public function test_rules_caps_cycles_and_allowed_accounts_split_the_same_way(): void
    {
        // Rent: fixed 400 a month, 150 already received this cycle -> tops up 250.
        $rent = $this->bucket('Rent', 0, ['kind' => BucketKind::Spending, 'cycle' => 'monthly', 'fill_rule' => FillRule::Fixed, 'fill_value' => 400]);
        $this->fund($rent, 150, '2026-11-02', AllocationSource::Income);
        // Groceries: fill to target 300, but 500 this cycle only; it holds 350 -> 150.
        $groceries = $this->bucket('Groceries', 5, ['kind' => BucketKind::Spending, 'category_id' => $this->food->id, 'cycle' => 'monthly',
            'fill_rule' => FillRule::FillToTarget, 'cap_amount' => 300]);
        $this->fund($groceries, 400, '2026-11-01');
        Transaction::factory()->for($this->user)->for($this->debit)->create(['type' => 'expense', 'category_id' => $this->food->id, 'amount' => 50, 'date' => '2026-11-03']);
        BucketCycle::create(['bucket_id' => $groceries->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'status' => BucketCycle::OPEN, 'cap_override' => 500]);
        // Stash: only from the cash account -> nothing from this debit income.
        $this->bucket('Stash', 7, ['fill_rule' => FillRule::Fixed, 'fill_value' => 999, 'allowed_account_ids' => [$this->cash->id]]);
        // Tax: 10% of the whole income -> 200.
        $this->bucket('Tax', 10, ['fill_rule' => FillRule::Percent, 'fill_value' => 10]);
        // Car: half of what is left, capped at 1,000 with 900 in it -> 100.
        $car = $this->bucket('Car', 20, ['fill_rule' => FillRule::PercentOfRemaining, 'fill_value' => 50, 'cap_amount' => 1000]);
        $this->fund($car, 900, '2026-10-20');
        // Done: completed, takes nothing.
        $this->bucket('Done', 25, ['fill_rule' => FillRule::Fixed, 'fill_value' => 300, 'completed_on' => '2026-11-05']);
        // Trip: half of what is left: 2,000 - 250 - 150 - 200 - 100 = 1,300 -> 650.
        $this->bucket('Trip', 30, ['fill_rule' => FillRule::PercentOfRemaining, 'fill_value' => 50]);

        [$predicted, $actual] = $this->predictAndRecord(2000, $this->debit);

        $this->assertSame(['Car' => '100.00', 'Groceries' => '150.00', 'Rent' => '250.00', 'Tax' => '200.00', 'Trip' => '650.00'], $actual);
        $this->assertSame($actual, $predicted);
    }

    public function test_a_step_up_that_already_applies_today_is_used(): void
    {
        // Emergency reached its 300 cap last month, so Holiday's step-up (250 instead of 100) applies this month.
        $emergency = $this->bucket('Emergency', 0, ['fill_rule' => FillRule::Fixed, 'fill_value' => 300, 'cap_amount' => 300]);
        $this->fund($emergency, 300, '2026-10-05');
        $this->bucket('Holiday', 10, ['cycle' => 'monthly', 'fill_rule' => FillRule::Fixed, 'fill_value' => 100,
            'step_up_value' => 250, 'step_up_after_bucket_id' => $emergency->id]);

        [$predicted, $actual] = $this->predictAndRecord(1000, $this->debit);

        $this->assertSame(['Holiday' => '250.00'], $actual);
        $this->assertSame($actual, $predicted);
    }

    public function test_a_never_funded_category_bucket_fills_to_its_target_without_refilling_past_spending(): void
    {
        // Never funded, 120 spent in its category since tracking started: that spending was a limit, not a hole in an
        // envelope, so filling to 300 takes 300 (its envelope starts from zero that day).
        $this->bucket('Dining', 0, ['kind' => BucketKind::Spending, 'category_id' => $this->food->id, 'cycle' => 'monthly',
            'fill_rule' => FillRule::FillToTarget, 'cap_amount' => 300, 'carry_over' => CarryOverPolicy::Carry]);
        Transaction::factory()->for($this->user)->for($this->debit)->create(['type' => 'expense', 'category_id' => $this->food->id, 'amount' => 120, 'date' => '2026-10-15']);

        [$predicted, $actual] = $this->predictAndRecord(1000, $this->debit);

        $this->assertSame(['Dining' => '300.00'], $actual);
        $this->assertSame($actual, $predicted);
    }

    public function test_a_plans_explicit_split_is_used_for_the_buckets_the_income_may_fill(): void
    {
        $rent = $this->bucket('Rent', 0, ['fill_rule' => FillRule::Fixed, 'fill_value' => 900]);
        $stash = $this->bucket('Stash', 10, ['allowed_account_ids' => [$this->cash->id]]);

        [$predicted, $actual] = $this->predictAndRecord(1000, $this->debit, [
            ['bucket_id' => $rent->id, 'amount' => '300'], ['bucket_id' => $stash->id, 'amount' => '200'],
        ]);

        $this->assertSame(['Rent' => '300.00'], $actual);
        $this->assertSame($actual, $predicted);
    }
}
