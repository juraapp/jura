<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Shared\Support\Money;
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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEvent(User $user, array $data): PlannedEvent
    {
        $event = new PlannedEvent($this->attributes($user, $data) + [
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
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

            $this->materialize($series, now()->addMonthsNoOverflow(self::SERIES_HORIZON_MONTHS));

            return $series;
        });
    }

    /**
     * Creates the missing occurrences of a series up to a date (or its end date).
     * Occurrences are computed from the series start, so deleted or skipped ones
     * are never recreated and month-end dates do not drift.
     */
    public function materialize(PlannedEventSeries $series, CarbonInterface $until): int
    {
        $limit = Carbon::instance($until)->startOfDay();
        if ($series->ends_on !== null && $series->ends_on->lt($limit)) {
            $limit = $series->ends_on->copy();
        }

        $lastDate = PlannedEvent::forUser($series->user_id)->where('series_id', $series->id)->max('date');
        $step = 0;
        if ($lastDate !== null) {
            $last = Carbon::parse($lastDate);
            while ($step < self::SERIES_MAX_OCCURRENCES && $series->frequency->occurrence($series->starts_on, $step)->lte($last)) {
                $step++;
            }
        }

        $created = 0;
        for (; $step < self::SERIES_MAX_OCCURRENCES; $step++) {
            $date = $series->frequency->occurrence($series->starts_on, $step);
            if ($date->gt($limit)) {
                break;
            }

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
            $created++;
        }

        return $created;
    }

    /**
     * Tops up every open-ended series to the standard horizon (daily command).
     */
    public function extendAllSeries(): int
    {
        $created = 0;
        $until = now()->addMonthsNoOverflow(self::SERIES_HORIZON_MONTHS);

        PlannedEventSeries::withoutGlobalScope('user')->chunkById(100, function ($batch) use (&$created, $until) {
            foreach ($batch as $series) {
                $created += $this->materialize($series, $until);
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

        $event->update($attributes + [
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
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
                    ->open()
                    ->where('date', '>=', $event->date->toDateString())
                    ->delete();

                $series->update(['ends_on' => $event->date->copy()->subDay()->toDateString()]);
            });

            return;
        }

        // A tombstone, not a delete, so extending the series never recreates it.
        $event->update(['status' => PlannedEventStatus::Skipped]);
    }

    public function skip(PlannedEvent $event): void
    {
        $event->update(['status' => PlannedEventStatus::Skipped]);
    }

    /**
     * Records the real transaction for a plan and closes it.
     *
     * @param  array{account_id: int, category_id: int, amount?: mixed, date?: string|null, description?: string|null}  $data
     */
    public function realize(PlannedEvent $event, array $data): int
    {
        return DB::transaction(function () use ($event, $data) {
            $transactionId = $this->transactions->record($event->user, $event->type, [
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'],
                'amount' => $data['amount'] ?? $event->amount->toDecimalString(),
                'date' => $data['date'] ?? today()->toDateString(),
                'description' => $data['description'] ?? $event->description,
                'notes' => $event->notes,
            ]);

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
        $event->update([
            'status' => PlannedEventStatus::Realized,
            'realized_transaction_id' => $transactionId,
        ]);
    }

    /**
     * Open planned expenses in one category and currency between two dates (inclusive).
     */
    public function plannedExpenseTotal(int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money
    {
        return Money::of(
            PlannedEvent::query()
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
    public function suggestedMatches(Collection $events, ?CarbonInterface $today = null): array
    {
        $today = Carbon::instance($today ?? now())->startOfDay();
        $linked = PlannedEvent::query()->whereNotNull('realized_transaction_id')->pluck('realized_transaction_id')->all();
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
                ->findSimilarTransactions($event->type, $event->currency, $event->amount, $from, $to, $linked)
                ->first();
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(User $user, array $data): array
    {
        $accountId = $data['account_id'] ?? null;
        $currency = ($accountId !== null ? $this->ledger->accountCurrency((int) $accountId) : null)
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
