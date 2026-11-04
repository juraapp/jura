<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Support\ProjectedItem;
use App\Modules\Planning\Support\Recurrence;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The single source of open planned money for every projection: the open stored plans up to a date, plus
 * the occurrences active repeating plans will create after the last one stored (series are only stored 12
 * months ahead, a projection may look further). Always scoped to the given user, so it works in jobs too.
 */
class PlannedOccurrences
{
    /** Safety bound on virtual occurrences per series (a weekly series over 36 months is about 160). */
    private const MAX_VIRTUAL = 1000;

    /**
     * @return Collection<int, ProjectedItem> sorted by date
     */
    public function open(int|User $user, CarbonInterface $until, ?string $currency = null, ?TransactionType $type = null): Collection
    {
        $userId = $user instanceof User ? $user->id : $user;
        $until = CarbonImmutable::instance($until)->startOfDay();
        $today = User::todayFor($user);

        $stored = PlannedEvent::forUser($userId)
            ->open()
            ->where('date', '<=', $until->toDateString())
            ->when($currency !== null, fn ($query) => $query->where('currency', $currency))
            ->when($type !== null, fn ($query) => $query->where('type', $type->value))
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn (PlannedEvent $event) => new ProjectedItem(
                $event->id,
                $event->series_id,
                $event->type,
                CarbonImmutable::parse($event->date->toDateString()),
                $event->amount,
                $event->currency,
                $event->account_id,
                $event->counter_account_id,
                $event->category_id,
                $event->certainty,
                $event->allocation_overrides,
                false,
            ));

        $series = PlannedEventSeries::forUser($userId)
            ->where('is_active', true)
            ->when($currency !== null, fn ($query) => $query->where('currency', $currency))
            ->when($type !== null, fn ($query) => $query->where('type', $type->value))
            ->withMax('events', 'date')
            ->get();

        $virtual = $series->flatMap(fn (PlannedEventSeries $rule) => array_map(
            fn (CarbonImmutable $date) => new ProjectedItem(
                null,
                $rule->id,
                $rule->type,
                $date,
                $rule->amount,
                $rule->currency,
                $rule->account_id,
                $rule->counter_account_id,
                $rule->category_id,
                $rule->certainty,
                $rule->allocation_overrides,
                true,
            ),
            Recurrence::occurrences(
                $rule->frequency,
                CarbonImmutable::parse($rule->starts_on->toDateString()),
                $rule->ends_on !== null ? CarbonImmutable::parse($rule->ends_on->toDateString()) : null,
                $rule->excluded_dates ?? [],
                $rule->events_max_date !== null ? CarbonImmutable::parse($rule->events_max_date) : null,
                $today,
                $until,
                self::MAX_VIRTUAL,
            ),
        ));

        return $stored->concat($virtual)
            ->sortBy(fn (ProjectedItem $item) => $item->date->format('Y-m-d').'-'.($item->virtual ? 1 : 0).'-'.str_pad((string) ($item->eventId ?? 0), 12, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Open planned expenses in one category and currency whose money moves between two dates (inclusive);
     * overdue plans count as today.
     */
    public function expenseTotal(int $userId, int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money
    {
        $today = User::todayFor($userId);
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->startOfDay();

        return $this->open($userId, $to, $currency, TransactionType::Expense)
            ->filter(fn (ProjectedItem $item) => $item->categoryId === $categoryId && $item->effectiveDate($today)->betweenIncluded($from, $to))
            ->reduce(fn ($total, ProjectedItem $item) => $total->add($item->amount), Money::zero());
    }
}
