<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Contracts\UpcomingPlans;
use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UpcomingPlansService implements UpcomingPlans
{
    public function __construct(
        private readonly PlannedEventService $planned,
        private readonly PlannedEventPresenter $presenter,
    ) {}

    public function listBands(User $user, int $days, array $filters = []): array
    {
        $today = $user->today()->toDateString();
        $until = $user->today()->addDays($days)->toDateString();

        $overdue = $this->filtered($user, $filters)->where('date', '<', $today);
        $upcoming = $this->filtered($user, $filters)->whereBetween('date', [$today, $until]);

        return [
            'overdue' => $this->band($user, $overdue, withMatches: true),
            'upcoming' => $this->band($user, $upcoming, withMatches: false),
        ];
    }

    public function provenance(User $user, array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        return PlannedEvent::forUser($user)
            ->whereIn('realized_transaction_id', $transactionIds)
            ->get(['id', 'series_id', 'realized_transaction_id'])
            ->mapWithKeys(fn (PlannedEvent $event) => [$event->realized_transaction_id => [
                'plannedEventId' => $event->id,
                'seriesId' => $event->series_id,
            ]])
            ->all();
    }

    public function needsReview(User $user, int $limit): array
    {
        $query = $this->reviewQuery($user);
        $count = (clone $query)->count();
        $events = $query->with(['account', 'counterAccount', 'category'])->orderBy('date')->orderBy('id')->limit($limit)->get();

        return ['count' => $count, 'items' => $this->present($user, $events, withMatches: true)];
    }

    public function needsReviewCount(User $user): int
    {
        return $this->reviewQuery($user)->count();
    }

    /**
     * @return Builder<PlannedEvent>
     */
    private function reviewQuery(User $user): Builder
    {
        $today = $user->today()->toDateString();

        return PlannedEvent::forUser($user)
            ->open()
            ->where(fn (Builder $query) => $query->where('date', '<', $today)->orWhereNotNull('post_error'));
    }

    /**
     * @param  array{account?: int|null, category?: int|null, type?: string|null}  $filters
     * @return Builder<PlannedEvent>
     */
    private function filtered(User $user, array $filters): Builder
    {
        return PlannedEvent::forUser($user)
            ->open()
            ->when($filters['account'] ?? null, fn (Builder $query, int $accountId) => $query->where(
                fn (Builder $either) => $either->where('account_id', $accountId)->orWhere('counter_account_id', $accountId)
            ))
            ->when($filters['category'] ?? null, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type));
    }

    /**
     * @param  Builder<PlannedEvent>  $query
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    private function band(User $user, Builder $query, bool $withMatches): array
    {
        $count = (clone $query)->count();
        $events = $query->with(['account', 'counterAccount', 'category'])->orderBy('date')->orderBy('id')->limit(self::BAND_LIMIT)->get();

        return ['count' => $count, 'items' => $this->present($user, $events, $withMatches)];
    }

    /**
     * @param  Collection<int, PlannedEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function present(User $user, Collection $events, bool $withMatches): array
    {
        $matches = $withMatches ? $this->planned->suggestedMatches($user, $events) : [];

        return $events->map(function (PlannedEvent $event) use ($matches) {
            $row = $this->presenter->event($event, $matches[$event->id] ?? null);
            $row['suggestedMatch'] = $row['suggestedMatch'] !== null ? (array) $row['suggestedMatch'] : null;

            return $row;
        })->values()->all();
    }
}
