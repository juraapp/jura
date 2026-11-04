<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Services\SeriesPoster;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecurringToSeriesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('frequency');
            $table->date('next_due_date');
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')->nullable()->constrained('recurring_transactions')->nullOnDelete();
        });

        $this->user = User::factory()->create(['timezone' => 'UTC']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'USD']);
        $this->category = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Rent']);
    }

    private function template(array $attributes = []): int
    {
        return DB::table('recurring_transactions')->insertGetId($attributes + [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 100,
            'description' => 'Rent',
            'frequency' => 'monthly',
            'next_due_date' => '2026-12-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function generated(int $templateId, string $date, bool $trashed = false): void
    {
        $transaction = Transaction::factory()->for($this->user)->for($this->account)->create([
            'category_id' => $this->category->id, 'date' => $date, 'is_recurring_generated' => true,
        ]);
        DB::table('transactions')->where('id', $transaction->id)->update(['recurring_transaction_id' => $templateId]);
        if ($trashed) {
            $transaction->delete();
        }
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_11_04_163211_migrate_recurring_transactions_to_series.php'))->up();
    }

    /**
     * @return list<string>
     */
    private function openDates(PlannedEventSeries $series): array
    {
        return PlannedEvent::where('series_id', $series->id)->open()->orderBy('date')->pluck('date')->map->toDateString()->all();
    }

    public function test_a_drifted_monthly_template_goes_back_to_its_day(): void
    {
        $this->template(['day_of_month' => 31, 'next_due_date' => '2026-11-30']);
        $inferred = $this->template(['description' => 'Inferred', 'next_due_date' => '2026-11-28']);
        $this->generated($inferred, '2026-07-31', trashed: true);
        $this->generated($inferred, '2026-08-31');

        $this->migrate();

        $byDay = PlannedEventSeries::where('description', 'Rent')->sole();
        $this->assertSame('2026-10-31', $byDay->starts_on->toDateString());
        $this->assertSame(['2026-11-30', '2026-12-31', '2027-01-31', '2027-02-28'], array_slice($this->openDates($byDay), 0, 4));

        $byFirst = PlannedEventSeries::where('description', 'Inferred')->sole();
        $this->assertSame(['2026-11-30', '2026-12-31'], array_slice($this->openDates($byFirst), 0, 2));
    }

    public function test_an_active_template_becomes_a_confirmed_auto_posting_series_up_to_twelve_months_ahead(): void
    {
        $this->template();

        $this->migrate();

        $series = PlannedEventSeries::sole();
        $this->assertTrue($series->auto_post);
        $this->assertTrue($series->is_active);
        $this->assertSame('confirmed', $series->certainty->value);
        $this->assertSame('USD', $series->currency);
        $this->assertSame('100.00', $series->amount->toDecimalString());
        $dates = $this->openDates($series);
        $this->assertSame('2026-12-01', $dates[0]);
        $this->assertSame('2027-11-01', end($dates));
        $this->assertSame(12, PlannedEvent::where('auto_post', true)->where('certainty', 'confirmed')->count());
    }

    public function test_a_template_that_was_behind_leaves_past_occurrences_that_are_all_posted(): void
    {
        $this->template(['next_due_date' => '2026-09-05', 'day_of_month' => 5]);

        $this->migrate();

        $this->assertSame(['2026-09-05', '2026-10-05', '2026-11-05'], array_slice($this->openDates(PlannedEventSeries::sole()), 0, 3));

        $this->assertSame(3, app(SeriesPoster::class)->postDue());
        $this->assertSame(['2026-09-05', '2026-10-05', '2026-11-05'], Transaction::orderBy('date')->pluck('date')->map->toDateString()->all());
        $this->assertTrue(Transaction::where('date', '2026-09-05')->sole()->is_recurring_generated);
    }

    public function test_paused_and_ended_templates_become_inactive_series_without_occurrences_and_a_future_end_is_kept(): void
    {
        $this->template(['description' => 'Paused', 'is_active' => false]);
        $this->template(['description' => 'Ended', 'end_date' => '2026-11-15']);
        $this->template(['description' => 'Ending', 'end_date' => '2027-02-15']);

        $this->migrate();

        $this->assertFalse(PlannedEventSeries::where('description', 'Paused')->sole()->is_active);
        $this->assertFalse(PlannedEventSeries::where('description', 'Ended')->sole()->is_active);
        $ending = PlannedEventSeries::where('description', 'Ending')->sole();
        $this->assertTrue($ending->is_active);
        $this->assertSame('2027-02-15', $ending->ends_on->toDateString());
        $this->assertSame(['2026-12-01', '2027-01-01', '2027-02-01'], $this->openDates($ending));
        $this->assertSame(3, PlannedEvent::count());
    }

    public function test_weekly_and_yearly_rhythms(): void
    {
        $this->template(['description' => 'Weekly', 'frequency' => 'weekly', 'next_due_date' => '2026-11-12']);
        $leap = $this->template(['description' => 'Leap', 'frequency' => 'yearly', 'next_due_date' => '2027-02-28']);
        $this->generated($leap, '2024-02-29');

        $this->migrate();

        $this->assertSame(['2026-11-12', '2026-11-19', '2026-11-26'], array_slice($this->openDates(PlannedEventSeries::where('description', 'Weekly')->sole()), 0, 3));
        $yearly = PlannedEventSeries::where('description', 'Leap')->sole();
        $this->assertSame('2024-02-29', $yearly->starts_on->toDateString());
        $this->assertSame(['2027-02-28'], $this->openDates($yearly));
    }

    public function test_a_missing_description_uses_the_category_and_a_trashed_account_keeps_its_currency(): void
    {
        $trashed = Account::factory()->for($this->user)->create(['currency' => 'EUR']);
        $this->template(['description' => null, 'account_id' => $trashed->id]);
        $trashed->delete();
        $this->category->delete();

        $this->migrate();

        $series = PlannedEventSeries::sole();
        $this->assertSame('Rent', $series->description);
        $this->assertSame('EUR', $series->currency);
    }

    public function test_each_template_stays_with_its_owner_and_the_old_column_and_table_are_dropped(): void
    {
        $other = User::factory()->create(['timezone' => 'UTC']);
        $otherAccount = Account::factory()->for($other)->create(['currency' => 'COP']);
        $this->template();
        $this->template(['user_id' => $other->id, 'account_id' => $otherAccount->id, 'description' => 'Theirs']);

        $this->migrate();

        $this->assertSame($other->id, PlannedEventSeries::where('description', 'Theirs')->sole()->user_id);
        $this->assertSame(12, PlannedEvent::where('user_id', $other->id)->count());
        $this->assertSame(12, PlannedEvent::where('user_id', $this->user->id)->count());
        $this->assertFalse(Schema::hasTable('recurring_transactions'));
        $this->assertFalse(Schema::hasColumn('transactions', 'recurring_transaction_id'));
        $this->assertTrue(Schema::hasColumn('transactions', 'is_recurring_generated'));
    }
}
