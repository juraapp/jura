<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Half of each frequency's period in days: how far a moved occurrence can be from its series date. */
    private const WINDOW_DAYS = ['weekly' => 3, 'biweekly' => 7, 'monthly' => 15, 'yearly' => 182];

    /**
     * Each occurrence of a repeating plan remembers the date its rule gave it (series_date), which stays when
     * the occurrence alone is moved, so rebuilding the plan's dates knows which date a confirmed or skipped
     * occurrence stands for.
     *
     * Backfill: an occurrence on a date of its series' current rule gets that date. One off the rule was moved
     * ("only this one"), or comes from an earlier rhythm: it gets the closest rule date within half a period
     * that no other occurrence of the series sits on (the later one on a tie), or its own date when there is
     * none. Occurrences of plans that do not repeat keep null.
     */
    public function up(): void
    {
        Schema::table('planned_events', function (Blueprint $table) {
            $table->date('series_date')->nullable()->after('series_id');
        });

        DB::table('planned_events')->whereNotNull('series_id')->update(['series_date' => DB::raw('date')]);

        DB::table('planned_event_series')->orderBy('id')->each(function (object $series) {
            $this->recoverMovedDates($series);
        });
    }

    private function recoverMovedDates(object $series): void
    {
        $events = DB::table('planned_events')->where('series_id', $series->id)->orderBy('date')->orderBy('id')->get(['id', 'date']);
        $window = self::WINDOW_DAYS[$series->frequency] ?? null;
        if ($events->isEmpty() || $window === null) {
            return;
        }

        $start = CarbonImmutable::parse($series->starts_on)->startOfDay();
        $last = CarbonImmutable::parse($events->last()->date)->addDays($window);
        $ruleDates = [];
        for ($step = 0; $step < 10000; $step++) {
            $date = $this->nth($series->frequency, $start, $step);
            if ($date->gt($last)) {
                break;
            }
            $ruleDates[] = $date;
        }

        $onRule = array_flip(array_map(fn (CarbonImmutable $date) => $date->toDateString(), $ruleDates));
        $taken = array_flip($events->pluck('date')->all());
        foreach ($events as $event) {
            if (isset($onRule[$event->date])) {
                continue;
            }

            $date = CarbonImmutable::parse($event->date);
            $closest = null;
            foreach ($ruleDates as $ruleDate) {
                $distance = abs((int) round($ruleDate->diffInDays($date)));
                if ($distance <= $window && ! isset($taken[$ruleDate->toDateString()])
                    && ($closest === null || $distance <= $closest[1])) {
                    $closest = [$ruleDate, $distance];
                }
            }

            if ($closest !== null) {
                $taken[$closest[0]->toDateString()] = true;
                DB::table('planned_events')->where('id', $event->id)->update(['series_date' => $closest[0]->toDateString()]);
            }
        }
    }

    /**
     * The Nth date of a rule, counted from its start (as App\Modules\Planning\Support\Recurrence does).
     */
    private function nth(string $frequency, CarbonImmutable $start, int $steps): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $start->addWeeks($steps),
            'biweekly' => $start->addWeeks(2 * $steps),
            'monthly' => $start->addMonthsNoOverflow($steps),
            default => $start->addYearsNoOverflow($steps),
        };
    }

    public function down(): void
    {
        Schema::table('planned_events', function (Blueprint $table) {
            $table->dropColumn('series_date');
        });
    }
};
