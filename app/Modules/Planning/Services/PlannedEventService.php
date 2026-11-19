<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Support\Recurrence;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to planned (projected) money. Planned events never touch
 * balances; realizing one records a real transaction through the ledger contract.
 */
class PlannedEventService
{
    public const SERIES_HORIZON_MONTHS = 12;

    public const SERIES_MAX_OCCURRENCES = 120;

    private const MATCH_WINDOW_DAYS = 5;

    private const MATCH_WINDOW_MAX_DAYS = 10;

    public function __construct(
        private readonly LedgerQuery $ledger,
        private readonly RecordsTransactions $transactions,
        private readonly IncomeAllocator $allocator,
        private readonly PlannedOccurrences $occurrences,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEvent(User $user, array $data): PlannedEvent
    {
        $event = new PlannedEvent($this->attributes($user, $data) + $this->extras($data) + [
            'date' => $data['date'],
        ]);
        $event->user_id = $user->id;
        $event->save();

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data  includes frequency and optional ends_on
     */
    public function createSeries(User $user, array $data): PlannedEventSeries
    {
        return DB::transaction(function () use ($user, $data) {
            $series = new PlannedEventSeries($this->attributes($user, $data) + $this->extras($data) + [
                'frequency' => $data['frequency'],
                'starts_on' => $data['date'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);
            $series->user_id = $user->id;
            $series->save();

            $today = $user->today();
            $this->materialize($series, $today->addMonthsNoOverflow(self::SERIES_HORIZON_MONTHS), $today);

            return $series;
        });
    }

    /**
     * Creates the missing occurrences of a series up to a date (or its end date): the dates after the last
     * one it created (materialized_until, not the latest stored occurrence, so moving one occurrence to
     * another date never brings its old date back nor skips the dates in between).
     * Occurrences are computed from the series start, so deleted ones (remembered in
     * the series' excluded dates) are never recreated and month-end dates do not drift.
     * Occurrences before $from (the owner's today) are never created: a series that
     * started in the past begins today instead of filling the month with overdue plans.
     */
    public function materialize(PlannedEventSeries $series, CarbonInterface $until, ?CarbonInterface $from = null): int
    {
        if ($series->is_active === false) {
            return 0;
        }

        $dates = Recurrence::occurrences(
            $series->frequency,
            CarbonImmutable::parse($series->starts_on->toDateString()),
            $series->ends_on !== null ? CarbonImmutable::parse($series->ends_on->toDateString()) : null,
            $series->excluded_dates ?? [],
            $this->materializedUntil($series),
            $from !== null ? CarbonImmutable::instance($from) : null,
            CarbonImmutable::instance($until),
            self::SERIES_MAX_OCCURRENCES,
        );

        foreach ($dates as $date) {
            $event = new PlannedEvent([
                'series_id' => $series->id,
                'type' => $series->type,
                'description' => $series->description,
                'amount' => $series->amount,
                'currency' => $series->currency,
                'date' => $date->toDateString(),
                'series_date' => $date->toDateString(),
                'account_id' => $series->account_id,
                'counter_account_id' => $series->counter_account_id,
                'category_id' => $series->category_id,
                'certainty' => $series->certainty,
                'notes' => $series->notes,
                'allocation_overrides' => $series->allocation_overrides,
                'auto_post' => (bool) $series->auto_post,
            ]);
            $event->user_id = $series->user_id;
            $event->save();
        }

        if ($dates !== []) {
            $series->forceFill(['materialized_until' => end($dates)->toDateString()])->save();
        }

        return count($dates);
    }

    /**
     * Prepares a series whose open occurrences from today on are gone (a new rhythm, or a resume) to be created
     * again from today: every date its rule gives from today comes back, except the ones occurrences already
     * confirmed or skipped stand for (Recurrence::claimed: the rule date closest to each one's series date, within
     * half of the shorter of the old and new periods). So a payment confirmed early or a skipped month is not
     * planned twice, and no other date is lost; the next date the user just typed always stays unless a closed
     * occurrence was for exactly that day. Claimed dates join the series' excluded dates, so later top-ups never
     * create them either. The candidates ignore the excluded dates, so running it again changes nothing.
     */
    private function rebuildFromToday(PlannedEventSeries $series, CarbonInterface $today, RecurringFrequency $previousFrequency, ?CarbonInterface $typedNextDate = null): void
    {
        $today = CarbonImmutable::instance($today)->startOfDay();
        $window = min($previousFrequency->halfPeriodDays(), $series->frequency->halfPeriodDays());
        $closed = PlannedEvent::forUser($series->user_id)
            ->where('series_id', $series->id)
            ->where('status', '!=', PlannedEventStatus::Planned->value)
            ->where(fn ($query) => $query->where('date', '>=', $today->toDateString())->orWhere('series_date', '>=', $today->toDateString()))
            ->get(['date', 'series_date'])
            ->map(fn (PlannedEvent $event) => ($event->series_date ?? $event->date)->toDateString())
            ->sort()
            ->values();

        $claimed = [];
        if ($closed->isNotEmpty()) {
            $candidates = Recurrence::occurrences(
                $series->frequency,
                CarbonImmutable::parse($series->starts_on->toDateString()),
                $series->ends_on !== null ? CarbonImmutable::parse($series->ends_on->toDateString()) : null,
                [],
                null,
                $today,
                CarbonImmutable::parse($closed->last())->addDays($window),
                1000,
            );
            $claimed = Recurrence::claimed($candidates, $closed->all(), $window, $typedNextDate !== null ? CarbonImmutable::instance($typedNextDate) : null);
        }

        $series->forceFill([
            'excluded_dates' => collect($series->excluded_dates ?? [])->merge($claimed)->unique()->sort()->values()->all(),
            'materialized_until' => $today->subDay()->toDateString(),
        ])->save();
    }

    private function materializedUntil(PlannedEventSeries $series): ?CarbonImmutable
    {
        return $series->materialized_until !== null ? CarbonImmutable::parse($series->materialized_until->toDateString()) : null;
    }

    /**
     * Forgets that a series created its dates after a given day (its open occurrences after it were deleted),
     * so a later top-up creates them again if the rule still gives them.
     */
    private function rewindTo(PlannedEventSeries $series, CarbonInterface $date): void
    {
        if ($series->materialized_until !== null && $series->materialized_until->gt($date)) {
            $series->forceFill(['materialized_until' => $date->toDateString()])->save();
        }
    }

    /**
     * The series now ends on a date: it forgets creating the dates after it, but occurrences already confirmed or
     * skipped for a date after it stay, so their series dates join the excluded dates and removing the end later
     * does not plan them again.
     */
    private function cutAt(PlannedEventSeries $series, CarbonInterface $endsOn): void
    {
        $kept = PlannedEvent::forUser($series->user_id)
            ->where('series_id', $series->id)
            ->where('status', '!=', PlannedEventStatus::Planned->value)
            ->get(['date', 'series_date'])
            ->map(fn (PlannedEvent $event) => ($event->series_date ?? $event->date)->toDateString())
            ->filter(fn (string $date) => $date > $endsOn->toDateString());

        if ($kept->isNotEmpty()) {
            $series->forceFill([
                'excluded_dates' => collect($series->excluded_dates ?? [])->merge($kept)->unique()->sort()->values()->all(),
            ])->save();
        }

        $this->rewindTo($series, $endsOn);
    }

    /**
     * Tops up every active series to the standard horizon, counted from each owner's today (daily command).
     */
    public function extendAllSeries(): int
    {
        $created = 0;

        PlannedEventSeries::withoutGlobalScope('user')->where('is_active', true)->with('user')->chunkById(100, function ($batch) use (&$created) {
            foreach ($batch as $series) {
                if ($series->user !== null) {
                    $today = $series->user->today();
                    $created += $this->materialize($series, $today->addMonthsNoOverflow(self::SERIES_HORIZON_MONTHS), $today);
                }
            }
        });

        return $created;
    }

    /**
     * The next date a series is planned for: its first open occurrence from today on, or, when none is
     * stored, the first date its rule gives from today. Null when the series has ended.
     */
    public function nextDate(PlannedEventSeries $series, ?CarbonInterface $today = null): ?CarbonImmutable
    {
        return $this->nextDates(collect([$series]), $today ?? $series->user->today())[$series->id];
    }

    /**
     * nextDate for several series of one owner, with one query.
     *
     * @param  Collection<int, PlannedEventSeries>  $series
     * @return array<int, CarbonImmutable|null> keyed by series id
     */
    public function nextDates(Collection $series, CarbonInterface $today): array
    {
        $today = CarbonImmutable::instance($today)->startOfDay();
        if ($series->isEmpty()) {
            return [];
        }

        $stored = PlannedEvent::forUser($series->first()->user_id)
            ->whereIn('series_id', $series->map(fn (PlannedEventSeries $rule) => $rule->id)->all())
            ->open()
            ->where('date', '>=', $today->toDateString())
            ->groupBy('series_id')
            ->toBase()
            ->selectRaw('series_id, min(date) as next_date')
            ->get()
            ->pluck('next_date', 'series_id');

        return $series->mapWithKeys(fn (PlannedEventSeries $rule) => [$rule->id => isset($stored[$rule->id])
            ? CarbonImmutable::parse($stored[$rule->id])
            : Recurrence::occurrences(
                $rule->frequency,
                CarbonImmutable::parse($rule->starts_on->toDateString()),
                $rule->ends_on !== null ? CarbonImmutable::parse($rule->ends_on->toDateString()) : null,
                $rule->excluded_dates ?? [],
                $this->materializedUntil($rule),
                $today,
                $today->addYears(2),
                1,
            )[0] ?? null,
        ])->all();
    }

    /**
     * Edits a whole repeating plan from the owner's today on. Overdue occurrences (before today) and closed
     * ones keep what they were. A new frequency or next date rebuilds the open occurrences from today, with
     * the next date as the new rhythm anchor (see rebuildFromToday); a stricter end date removes open
     * occurrences after it.
     *
     * @param  array<string, mixed>  $data  plan fields plus frequency, next_date and optional ends_on
     */
    public function updateSeries(PlannedEventSeries $series, array $data): void
    {
        $user = $series->user;
        $today = $user->today();
        $nextDate = CarbonImmutable::parse($data['next_date']);
        $current = $this->nextDate($series, $today);
        $previousFrequency = $series->frequency;
        $rescheduled = $data['frequency'] !== $series->frequency->value
            || $current === null
            || ! $nextDate->isSameDay($current);
        $attributes = $this->attributes($user, ['type' => $series->type->value] + $data) + $this->extras($data);

        DB::transaction(function () use ($series, $data, $today, $nextDate, $rescheduled, $attributes, $previousFrequency) {
            $series->fill($attributes + [
                'frequency' => $data['frequency'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);

            if ($rescheduled) {
                $series->starts_on = $nextDate->toDateString();
                $series->excluded_dates = collect($series->excluded_dates ?? [])
                    ->filter(fn (string $date) => $date < $today->toDateString())
                    ->values()
                    ->all();
            }
            $series->save();

            $upcoming = PlannedEvent::forUser($series->user_id)
                ->where('series_id', $series->id)
                ->open()
                ->where('date', '>=', $today->toDateString());

            if ($rescheduled) {
                $upcoming->delete();
                $this->rebuildFromToday($series, $today, $previousFrequency, $nextDate);
            } else {
                $upcoming->get()->each(fn (PlannedEvent $occurrence) => $occurrence->update($attributes + ['post_error' => null]));
            }

            if ($series->ends_on !== null) {
                PlannedEvent::forUser($series->user_id)
                    ->where('series_id', $series->id)
                    ->open()
                    ->where('date', '>', $series->ends_on->toDateString())
                    ->delete();
                $this->cutAt($series, $series->ends_on);
            }

            if (! $series->auto_post) {
                PlannedEvent::forUser($series->user_id)->where('series_id', $series->id)->open()->update(['auto_post' => false]);
            }

            $this->materialize($series, $today->addMonthsNoOverflow(self::SERIES_HORIZON_MONTHS), $today);
        });
    }

    /**
     * Stops a repeating plan: its upcoming occurrences go away and the overdue ones stay as reminders but
     * no longer record themselves.
     */
    public function pauseSeries(PlannedEventSeries $series): void
    {
        $today = $series->user->today();

        DB::transaction(function () use ($series, $today) {
            $series->update(['is_active' => false]);

            $open = fn () => PlannedEvent::forUser($series->user_id)->where('series_id', $series->id)->open();
            $open()->where('date', '>=', $today->toDateString())->delete();
            $open()->update(['auto_post' => false]);
            $this->rewindTo($series, $today->subDay());
        });
    }

    /**
     * Starts a paused plan again from today. Dates missed while it was paused are never created, so nothing
     * is recorded for the past, and a date already confirmed or skipped is not planned again.
     */
    public function resumeSeries(PlannedEventSeries $series): void
    {
        $today = $series->user->today();

        DB::transaction(function () use ($series, $today) {
            $series->update(['is_active' => true]);
            $this->rebuildFromToday($series, $today, $series->frequency);
            $this->materialize($series, $today->addMonthsNoOverflow(self::SERIES_HORIZON_MONTHS), $today);
        });
    }

    /**
     * Deletes a repeating plan and its open occurrences. Realized and skipped occurrences stay as history
     * (their series link is cleared by the foreign key).
     */
    public function deleteSeries(PlannedEventSeries $series): void
    {
        DB::transaction(function () use ($series) {
            PlannedEvent::forUser($series->user_id)->where('series_id', $series->id)->open()->delete();
            $series->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  'this'|'future'  $scope
     */
    public function updateEvent(PlannedEvent $event, array $data, string $scope): void
    {
        $user = $event->user;
        $attributes = $this->attributes($user, $data);

        if ($scope === 'future' && $event->series !== null) {
            $attributes += $this->extras($data);

            DB::transaction(function () use ($event, $attributes) {
                $event->series->update($attributes);

                PlannedEvent::forUser($event->user_id)
                    ->where('series_id', $event->series_id)
                    ->open()
                    ->where('date', '>=', $event->date->toDateString())
                    ->get()
                    ->each(fn (PlannedEvent $occurrence) => $occurrence->update($attributes + ['post_error' => null]));
            });

            return;
        }

        // An open plan still pointing at a transaction in the Trash was changed: it no longer describes that transaction.
        $unlink = $event->status !== PlannedEventStatus::Realized && $event->realized_transaction_id !== null
            ? ['realized_transaction_id' => null]
            : [];

        // The edit may have fixed what blocked the automatic post; the next try says again if not.
        $event->update($attributes + $unlink + $this->extras($data) + [
            'date' => $data['date'],
            'post_error' => null,
        ]);
    }

    /**
     * @param  'this'|'future'  $scope
     */
    public function deleteEvent(PlannedEvent $event, string $scope): void
    {
        $series = $event->series;

        if ($series === null) {
            $event->delete();

            return;
        }

        if ($scope === 'future') {
            DB::transaction(function () use ($event, $series) {
                PlannedEvent::forUser($event->user_id)
                    ->where('series_id', $series->id)
                    ->where('status', '!=', PlannedEventStatus::Realized->value)
                    ->where('date', '>=', $event->date->toDateString())
                    ->delete();

                $series->update(['ends_on' => $event->date->copy()->subDay()->toDateString()]);
                $this->cutAt($series, $series->ends_on);
            });

            return;
        }

        // The date the rule gave it, also when it was moved: that is the date that must not come back.
        DB::transaction(function () use ($event, $series) {
            $date = ($event->series_date ?? $event->date)->toDateString();
            $series->update(['excluded_dates' => collect($series->excluded_dates ?? [])->push($date)->unique()->sort()->values()->all()]);
            $event->delete();
        });
    }

    public function reopen(PlannedEvent $event): void
    {
        $event->update(['status' => PlannedEventStatus::Planned]);
    }

    /**
     * Skipping a plan whose transaction is in the Trash also forgets that transaction, so restoring it
     * later neither realizes the plan again nor splits the income the plan's way.
     */
    public function skip(PlannedEvent $event): void
    {
        $event->update(['status' => PlannedEventStatus::Skipped, 'realized_transaction_id' => null]);
    }

    /**
     * Records the real transaction for a plan and closes it. Anything left out comes from the plan: its
     * account, category, amount and description, and its date (today when the plan is dated later). The plan
     * is locked and re-read first, so a plan already closed (by another click or by the auto-poster) records
     * nothing and returns null.
     *
     * @param  array{account_id?: int|null, counter_account_id?: int|null, category_id?: int|null, amount?: mixed, date?: string|null, description?: string|null}  $data
     */
    public function realize(PlannedEvent $event, array $data = [], bool $autoPosted = false): ?int
    {
        return DB::transaction(function () use ($event, $data, $autoPosted) {
            $current = PlannedEvent::withoutGlobalScope('user')->lockForUpdate()->find($event->id);
            if ($current === null || $current->status !== PlannedEventStatus::Planned) {
                return null;
            }

            $user = $current->user;
            $today = $user->today();
            $accountId = $data['account_id'] ?? $current->account_id;
            $counterAccountId = $current->isTransfer() ? ($data['counter_account_id'] ?? $current->counter_account_id) : null;
            $categoryId = $current->isTransfer() ? null : ($data['category_id'] ?? $current->category_id);
            $date = $data['date'] ?? ($current->date->gt($today) ? $today : $current->date)->toDateString();
            $amount = $data['amount'] ?? $current->amount->toDecimalString();
            $description = $data['description'] ?? $current->description;

            if ($current->isTransfer()) {
                // The outgoing leg's id identifies the transfer; deleting or restoring either leg moves both.
                $transactionId = $this->transactions->recordTransfer($user, [
                    'from_account_id' => $accountId,
                    'to_account_id' => $counterAccountId,
                    'amount' => $amount,
                    'date' => $date,
                    'description' => $description,
                    'notes' => $current->notes,
                ], $autoPosted);
            } else {
                $record = fn () => $this->transactions->record($user, $current->type, [
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'amount' => $amount,
                    'date' => $date,
                    'description' => $description,
                    'notes' => $current->notes,
                ], $autoPosted);

                $transactionId = $current->isIncome() && ! empty($current->allocation_overrides)
                    ? $this->allocator->withOverrides($current->allocation_overrides, $record)
                    : $record();
            }

            $current->update([
                'status' => PlannedEventStatus::Realized,
                'realized_transaction_id' => $transactionId,
                'account_id' => $accountId,
                'counter_account_id' => $counterAccountId,
                'category_id' => $categoryId,
                'post_error' => null,
            ]);
            $event->setRawAttributes($current->getAttributes(), true);

            return $transactionId;
        });
    }

    /**
     * Closes a plan against a real transaction that already exists.
     */
    public function link(PlannedEvent $event, int $transactionId): void
    {
        DB::transaction(function () use ($event, $transactionId) {
            $event->update([
                'status' => PlannedEventStatus::Realized,
                'realized_transaction_id' => $transactionId,
            ]);

            if (! $event->isIncome() || empty($event->allocation_overrides)) {
                return;
            }

            $income = $this->ledger->income($event->user_id, $transactionId);
            if ($income !== null) {
                $this->allocator->reallocate($event->user, $transactionId, $income['account_id'], $income['amount'], $income['currency'], $income['date']);
            }
        });
    }

    /**
     * The real transaction behind a realized plan went to the Trash: the plan is open again. Its
     * realized_transaction_id is kept, so restoring the transaction can close it again.
     */
    public function transactionDeleted(User $user, int $transactionId): void
    {
        PlannedEvent::forUser($user)
            ->where('realized_transaction_id', $transactionId)
            ->where('status', PlannedEventStatus::Realized->value)
            ->update(['status' => PlannedEventStatus::Planned->value]);
    }

    /**
     * The transaction is back: a plan still open and not realized by another transaction in the meantime
     * is realized again.
     */
    public function transactionRestored(User $user, int $transactionId): void
    {
        PlannedEvent::forUser($user)
            ->where('realized_transaction_id', $transactionId)
            ->where('status', PlannedEventStatus::Planned->value)
            ->update(['status' => PlannedEventStatus::Realized->value]);
    }

    /**
     * The transaction is gone for good: forget the link, and reopen a plan that was still marked realized. A plan
     * left open no longer records itself (once the link is gone nothing else tells it was already recorded): it
     * waits in needs review for the user.
     */
    public function transactionPurged(User $user, int $transactionId): void
    {
        PlannedEvent::forUser($user)
            ->where('realized_transaction_id', $transactionId)
            ->whereIn('status', [PlannedEventStatus::Realized->value, PlannedEventStatus::Planned->value])
            ->update(['status' => PlannedEventStatus::Planned->value, 'auto_post' => false]);

        PlannedEvent::forUser($user)
            ->where('realized_transaction_id', $transactionId)
            ->update(['realized_transaction_id' => null]);
    }

    /**
     * Open planned expenses in one category and currency between two dates (inclusive); overdue ones count as today.
     */
    public function plannedExpenseTotal(int $userId, int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money
    {
        return $this->occurrences->expenseTotal($userId, $categoryId, $currency, $from, $to);
    }

    /**
     * The closest similar real transaction for each open event that is due soon or overdue.
     *
     * @param  Collection<int, PlannedEvent>  $events
     * @return array<int, \stdClass|null> keyed by planned event id
     */
    public function suggestedMatches(User $user, Collection $events, ?CarbonInterface $today = null): array
    {
        $today = Carbon::instance($today ?? $user->today())->startOfDay();
        $linked = PlannedEvent::forUser($user)->whereNotNull('realized_transaction_id')->pluck('realized_transaction_id')->all();
        $matches = [];

        foreach ($events as $event) {
            if ($event->status !== PlannedEventStatus::Planned || $event->date->gt($today->copy()->addDays(self::MATCH_WINDOW_DAYS))) {
                continue;
            }

            $from = $event->date->copy()->subDays(self::MATCH_WINDOW_DAYS);
            // Up to today for an overdue plan, but never more than MATCH_WINDOW_MAX_DAYS after its date.
            $to = $event->date->copy()->addDays(self::MATCH_WINDOW_DAYS);
            if ($to->lt($today)) {
                $to = $today->copy()->min($event->date->copy()->addDays(self::MATCH_WINDOW_MAX_DAYS));
            }

            $matches[$event->id] = $this->ledger
                ->findSimilarTransactions($user->id, $event->type, $event->currency, $event->amount, $from, $to, $linked)
                ->first();
        }

        return $matches;
    }

    /**
     * The fields a plan and a repeating plan share beyond what it is: notes, an income's bucket split and
     * whether it records itself on its date.
     *
     * @param  array<string, mixed>  $data
     * @return array{notes: string|null, allocation_overrides: list<array{bucket_id: int, amount: string}>|null, auto_post: bool}
     */
    private function extras(array $data): array
    {
        return [
            'notes' => $data['notes'] ?? null,
            'allocation_overrides' => $this->overrides($data),
            'auto_post' => (bool) ($data['auto_post'] ?? false),
        ];
    }

    /**
     * Explicit per-bucket amounts for an income, or null (let the bucket rules decide).
     *
     * @param  array<string, mixed>  $data
     * @return list<array{bucket_id: int, amount: string}>|null
     */
    private function overrides(array $data): ?array
    {
        if (($data['type'] ?? null) !== 'income' || empty($data['allocation_overrides'])) {
            return null;
        }

        return collect($data['allocation_overrides'])
            ->map(fn (array $row) => ['bucket_id' => (int) $row['bucket_id'], 'amount' => Money::of($row['amount'])->toDecimalString()])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(User $user, array $data): array
    {
        $accountId = $data['account_id'] ?? null;
        $currency = ($accountId !== null ? $this->ledger->accountCurrency($user->id, (int) $accountId) : null)
            ?? $data['currency'] ?? $user->currency_default;
        $transfer = in_array($data['type'], ['transfer', TransactionType::TransferOut->value], true);

        return [
            'type' => $transfer ? TransactionType::TransferOut->value : $data['type'],
            'description' => $data['description'],
            'amount' => Money::of($data['amount']),
            'currency' => $currency,
            'account_id' => $accountId,
            'counter_account_id' => $transfer ? ($data['counter_account_id'] ?? null) : null,
            'category_id' => $transfer ? null : ($data['category_id'] ?? null),
            'certainty' => $data['certainty'] ?? 'expected',
        ];
    }
}
