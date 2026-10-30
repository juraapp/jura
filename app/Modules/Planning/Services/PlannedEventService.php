<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\PlannedEventStatus;
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

    public function __construct(
        private readonly LedgerQuery $ledger,
        private readonly RecordsTransactions $transactions,
        private readonly IncomeAllocator $allocator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEvent(User $user, array $data): PlannedEvent
    {
        $event = new PlannedEvent($this->attributes($user, $data) + [
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
            'allocation_overrides' => $this->overrides($data),
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
            $series = new PlannedEventSeries($this->attributes($user, $data) + [
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
     * Creates the missing occurrences of a series up to a date (or its end date).
     * Occurrences are computed from the series start, so deleted ones (remembered in
     * the series' excluded dates) are never recreated and month-end dates do not drift.
     * Occurrences before $from (the owner's today) are never created: a series that
     * started in the past begins today instead of filling the month with overdue plans.
     */
    public function materialize(PlannedEventSeries $series, CarbonInterface $until, ?CarbonInterface $from = null): int
    {
        $lastDate = PlannedEvent::forUser($series->user_id)->where('series_id', $series->id)->max('date');

        $dates = Recurrence::occurrences(
            $series->frequency,
            CarbonImmutable::parse($series->starts_on->toDateString()),
            $series->ends_on !== null ? CarbonImmutable::parse($series->ends_on->toDateString()) : null,
            $series->excluded_dates ?? [],
            $lastDate !== null ? CarbonImmutable::parse($lastDate) : null,
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
                'account_id' => $series->account_id,
                'category_id' => $series->category_id,
                'certainty' => $series->certainty,
            ]);
            $event->user_id = $series->user_id;
            $event->save();
        }

        return count($dates);
    }

    /**
     * Tops up every open-ended series to the standard horizon, counted from each owner's today (daily command).
     */
    public function extendAllSeries(): int
    {
        $created = 0;

        PlannedEventSeries::withoutGlobalScope('user')->with('user')->chunkById(100, function ($batch) use (&$created) {
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
     * @param  array<string, mixed>  $data
     * @param  'this'|'future'  $scope
     */
    public function updateEvent(PlannedEvent $event, array $data, string $scope): void
    {
        $user = $event->user;
        $attributes = $this->attributes($user, $data);

        if ($scope === 'future' && $event->series !== null) {
            DB::transaction(function () use ($event, $attributes) {
                $event->series->update($attributes);

                PlannedEvent::forUser($event->user_id)
                    ->where('series_id', $event->series_id)
                    ->open()
                    ->where('date', '>=', $event->date->toDateString())
                    ->get()
                    ->each(fn (PlannedEvent $occurrence) => $occurrence->update($attributes));
            });

            return;
        }

        // An open plan still pointing at a transaction in the Trash was changed: it no longer describes that transaction.
        $unlink = $event->status !== PlannedEventStatus::Realized && $event->realized_transaction_id !== null
            ? ['realized_transaction_id' => null]
            : [];

        $event->update($attributes + $unlink + [
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
            'allocation_overrides' => $this->overrides($data),
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
            });

            return;
        }

        DB::transaction(function () use ($event, $series) {
            $date = $event->date->toDateString();
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
     * Records the real transaction for a plan and closes it.
     *
     * @param  array{account_id: int, category_id: int, amount?: mixed, date?: string|null, description?: string|null}  $data
     */
    public function realize(PlannedEvent $event, array $data): int
    {
        return DB::transaction(function () use ($event, $data) {
            $record = fn () => $this->transactions->record($event->user, $event->type, [
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'],
                'amount' => $data['amount'] ?? $event->amount->toDecimalString(),
                'date' => $data['date'] ?? $event->user->today()->toDateString(),
                'description' => $data['description'] ?? $event->description,
                'notes' => $event->notes,
            ]);

            $transactionId = $event->isIncome() && ! empty($event->allocation_overrides)
                ? $this->allocator->withOverrides($event->allocation_overrides, $record)
                : $record();

            $event->update([
                'status' => PlannedEventStatus::Realized,
                'realized_transaction_id' => $transactionId,
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'],
            ]);

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
     * The transaction is gone for good: forget the link, and reopen a plan that was still marked realized.
     */
    public function transactionPurged(User $user, int $transactionId): void
    {
        PlannedEvent::forUser($user)
            ->where('realized_transaction_id', $transactionId)
            ->where('status', PlannedEventStatus::Realized->value)
            ->update(['status' => PlannedEventStatus::Planned->value]);

        PlannedEvent::forUser($user)
            ->where('realized_transaction_id', $transactionId)
            ->update(['realized_transaction_id' => null]);
    }

    /**
     * Open planned expenses in one category and currency between two dates (inclusive).
     */
    public function plannedExpenseTotal(int $userId, int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money
    {
        return Money::of(
            PlannedEvent::forUser($userId)
                ->open()
                ->where('type', TransactionType::Expense->value)
                ->where('category_id', $categoryId)
                ->where('currency', $currency)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->sum('amount')
        );
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
            $to = $event->date->copy()->addDays(self::MATCH_WINDOW_DAYS);
            if ($to->lt($today)) {
                $to = $today->copy();
            }

            $matches[$event->id] = $this->ledger
                ->findSimilarTransactions($user->id, $event->type, $event->currency, $event->amount, $from, $to, $linked)
                ->first();
        }

        return $matches;
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

        return [
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => Money::of($data['amount']),
            'currency' => $currency,
            'account_id' => $accountId,
            'category_id' => $data['category_id'] ?? null,
            'certainty' => $data['certainty'] ?? 'expected',
        ];
    }
}
