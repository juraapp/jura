<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring transactions become repeating plans that record themselves (auto_post). Each template becomes a
 * confirmed series anchored on its real rhythm (a monthly template that drifted to the 28th after February
 * goes back to its day, such as the 31st); an active one gets its open occurrences from its next due date up
 * to 12 months after its owner's today, so a template that was behind leaves past occurrences that the
 * poster records on their own dates. Then transactions.recurring_transaction_id and the table are dropped;
 * transactions keep is_recurring_generated ("auto-posted").
 *
 * Irreversible. Drain the queue before deploying, so no recurring job runs against the dropped table.
 */
return new class extends Migration
{
    private const HORIZON_MONTHS = 12;

    private const MAX_OCCURRENCES = 120;

    public function up(): void
    {
        if (! Schema::hasTable('recurring_transactions')) {
            return;
        }

        DB::transaction(function () {
            DB::table('recurring_transactions')
                ->join('accounts', 'accounts.id', '=', 'recurring_transactions.account_id')
                ->join('users', 'users.id', '=', 'recurring_transactions.user_id')
                ->leftJoin('categories', 'categories.id', '=', 'recurring_transactions.category_id')
                ->select('recurring_transactions.*', 'accounts.currency', 'users.timezone', 'categories.name as category_name')
                ->orderBy('recurring_transactions.id')
                ->chunk(100, function ($rows) {
                    foreach ($rows as $row) {
                        $this->migrate($row);
                    }
                });
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['recurring_transaction_id']);
            $table->dropColumn('recurring_transaction_id');
        });

        Schema::dropIfExists('recurring_transactions');
    }

    public function down(): void
    {
        throw new RuntimeException('Recurring transactions were turned into repeating plans; this migration cannot be rolled back.');
    }

    private function migrate(object $row): void
    {
        $nextDue = CarbonImmutable::parse($row->next_due_date)->startOfDay();
        $endsOn = $row->end_date !== null ? CarbonImmutable::parse($row->end_date)->startOfDay() : null;
        $firstDate = DB::table('transactions')->where('recurring_transaction_id', $row->id)->min('date');
        $first = $firstDate !== null ? CarbonImmutable::parse($firstDate) : null;
        $startsOn = $this->anchor($row->frequency, $nextDue, $first, $row->day_of_month !== null ? (int) $row->day_of_month : null);
        $active = (bool) $row->is_active && ($endsOn === null || $endsOn->gte($nextDue));
        $now = now();

        $attributes = [
            'user_id' => $row->user_id,
            'type' => $row->type,
            'description' => $row->description ?? $row->category_name ?? '',
            'amount' => $row->amount,
            'currency' => $row->currency,
            'account_id' => $row->account_id,
            'category_id' => $row->category_id,
            'certainty' => 'confirmed',
        ];

        $seriesId = DB::table('planned_event_series')->insertGetId($attributes + [
            'frequency' => $row->frequency,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn?->toDateString(),
            'auto_post' => true,
            'is_active' => $active,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! $active) {
            return;
        }

        $until = $this->today($row->timezone)->addMonthsNoOverflow(self::HORIZON_MONTHS);
        if ($endsOn !== null && $endsOn->lt($until)) {
            $until = $endsOn;
        }

        $occurrences = [];
        for ($step = 0; $step < 10000 && count($occurrences) < self::MAX_OCCURRENCES; $step++) {
            $date = $this->nth($row->frequency, $startsOn, $step);
            if ($date->gt($until)) {
                break;
            }
            if ($date->lt($nextDue)) {
                continue;
            }

            $occurrences[] = $attributes + [
                'series_id' => $seriesId,
                'date' => $date->toDateString(),
                'status' => 'planned',
                'auto_post' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($occurrences, 100) as $batch) {
            DB::table('planned_events')->insert($batch);
        }
    }

    /**
     * The date the rhythm is counted from. Weekly rhythms keep the next due date. A monthly one keeps the day
     * of the month the template was set to (or that its first transaction fell on) in the next due date's
     * month, or the closest earlier month that has that day. A yearly one keeps the first transaction's month
     * and day in the latest year up to the next due date's year that has it (a 29 February needs a leap year).
     */
    private function anchor(string $frequency, CarbonImmutable $nextDue, ?CarbonImmutable $first, ?int $dayOfMonth): CarbonImmutable
    {
        if ($frequency === 'monthly') {
            $day = $dayOfMonth ?? $first?->day ?? $nextDue->day;
            $month = $nextDue->startOfMonth();
            for ($i = 0; $i < 12 && $month->daysInMonth < $day; $i++) {
                $month = $month->subMonthNoOverflow();
            }

            return $month->setDay(min($day, $month->daysInMonth));
        }

        if ($frequency === 'yearly') {
            $source = $first ?? $nextDue;
            for ($year = $nextDue->year; $year > $nextDue->year - 8; $year--) {
                if (checkdate($source->month, $source->day, $year)) {
                    return CarbonImmutable::create($year, $source->month, $source->day)->startOfDay();
                }
            }

            return $nextDue;
        }

        return $nextDue;
    }

    private function nth(string $frequency, CarbonImmutable $start, int $steps): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $start->addWeeks($steps),
            'biweekly' => $start->addWeeks(2 * $steps),
            'yearly' => $start->addYearsNoOverflow($steps),
            default => $start->addMonthsNoOverflow($steps),
        };
    }

    /**
     * The owner's calendar date (their timezone, else the app's), as midnight in the app timezone.
     */
    private function today(?string $timezone): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::now($timezone ?: config('app.timezone'))->toDateString();
        } catch (Throwable) {
            $date = CarbonImmutable::now(config('app.timezone'))->toDateString();
        }

        return CarbonImmutable::parse($date);
    }
};
