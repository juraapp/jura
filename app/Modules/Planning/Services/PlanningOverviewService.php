<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Contracts\PlanningOverview;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read models for the planning reports: nothing here changes data.
 */
class PlanningOverviewService implements PlanningOverview
{
    public function __construct(
        private readonly BucketLedger $funds,
        private readonly PlannerService $planner,
    ) {}

    public function bucketsByCurrency(User $user): array
    {
        return $this->buckets($user)
            ->groupBy('currency')
            ->sortKeys()
            ->map(fn (Collection $buckets) => $buckets->map(fn (Bucket $bucket) => [
                'id' => $bucket->id,
                'name' => $bucket->name,
                'kind' => $bucket->kind->value,
                'isDefault' => $bucket->is_default,
                'balance' => $this->funds->balance($bucket)->toFloat(),
                'available' => $this->funds->available($bucket)->toFloat(),
                'cap' => $bucket->cap_amount?->toFloat(),
            ])->values()->all())
            ->all();
    }

    public function allocationHistory(User $user, int $months): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths($months - 1);
        $keys = collect(range(0, $months - 1))->map(fn (int $i) => $start->copy()->addMonths($i));
        $labels = $keys->map(fn (Carbon $month) => $month->translatedFormat('M Y'))->all();

        $buckets = $this->buckets($user)->keyBy('id');

        $totals = Allocation::forUser($user)
            ->where('date', '>=', $start->toDateString())
            ->selectRaw("bucket_id, DATE_FORMAT(date, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('bucket_id', 'ym')
            ->get()
            ->groupBy('bucket_id');

        $result = [];
        foreach ($buckets->groupBy('currency')->sortKeys() as $currency => $currencyBuckets) {
            $series = [];

            foreach ($currencyBuckets as $bucket) {
                $byMonth = ($totals->get($bucket->id) ?? collect())->keyBy('ym');
                if ($byMonth->isEmpty()) {
                    continue;
                }

                $series[] = [
                    'name' => $bucket->name,
                    'data' => $keys->map(fn (Carbon $month) => (float) ($byMonth->get($month->format('Y-m'))->total ?? 0))->all(),
                ];
            }

            $result[$currency] = ['labels' => $labels, 'series' => $series];
        }

        return $result;
    }

    public function projectedBucketBalances(User $user, ForecastView $view, int $months): array
    {
        $result = [];

        foreach ($this->buckets($user)->pluck('currency')->unique()->sort() as $currency) {
            $context = $this->planner->context($user, $currency, $view, $months);
            $buckets = $this->planner->simulate($context, [])['buckets'];

            $result[$currency] = array_map(fn (array $bucket) => [
                'id' => $bucket['id'],
                'name' => $bucket['name'],
                'now' => $bucket['startBalance'],
                'projected' => $bucket['endBalance'],
            ], $buckets);
        }

        return $result;
    }

    public function projectSummaries(User $user, ForecastView $view, int $months): array
    {
        $items = WishlistItem::forUser($user)->with(['bucket', 'project'])->orderBy('id')->get();
        $groups = $items->groupBy(fn (WishlistItem $item) => $item->project_id ?? 0);

        $summaries = [];
        foreach ($groups as $projectId => $groupItems) {
            $project = $groupItems->first()->project;

            $summaries[] = [
                'id' => $project?->id,
                'name' => $project?->name,
                'kind' => $project?->kind->value,
                'kindLabel' => $project?->kind->label(),
                'color' => $project?->color,
                'counts' => [
                    'wanted' => $groupItems->where('status', WishlistStatus::Wanted)->count(),
                    'purchased' => $groupItems->where('status', WishlistStatus::Purchased)->count(),
                    'dropped' => $groupItems->where('status', WishlistStatus::Dropped)->count(),
                ],
                'totals' => $this->totals($user, $groupItems, $view, $months),
            ];
        }

        usort($summaries, fn (array $a, array $b) => [$a['name'] === null, $a['name']] <=> [$b['name'] === null, $b['name']]);

        return $summaries;
    }

    /**
     * @param  Collection<int, WishlistItem>  $items
     * @return list<array{currency: string, wanted: float, purchased: float, fundedPercent: float, completion: string|null}>
     */
    private function totals(User $user, Collection $items, ForecastView $view, int $months): array
    {
        $totals = [];

        foreach ($items->groupBy('currency')->sortKeys() as $currency => $currencyItems) {
            $wanted = $currencyItems->where('status', WishlistStatus::Wanted);
            $wantedTotal = $wanted->reduce(fn (Money $sum, WishlistItem $item) => $sum->add($item->price), Money::zero());
            $purchasedTotal = $currencyItems->where('status', WishlistStatus::Purchased)
                ->reduce(fn (Money $sum, WishlistItem $item) => $sum->add($item->price), Money::zero());

            $covered = Money::zero();
            foreach ($wanted as $item) {
                if ($item->bucket !== null) {
                    $available = $this->funds->available($item->bucket);
                    $covered = $covered->add($available->isPositive() ? ($available->lessThan($item->price) ? $available : $item->price) : Money::zero());
                }
            }

            $totals[] = [
                'currency' => $currency,
                'wanted' => $wantedTotal->toFloat(),
                'purchased' => $purchasedTotal->toFloat(),
                'fundedPercent' => $wantedTotal->isPositive() ? round($covered->percentageOf($wantedTotal), 1) : 0.0,
                'completion' => $this->completion($user, $wanted, $currency, $view, $months),
            ];
        }

        return $totals;
    }

    /**
     * The date the last wanted item could be bought, following the planner's best order; null if some cannot be bought in time.
     *
     * @param  Collection<int, WishlistItem>  $wanted
     */
    private function completion(User $user, Collection $wanted, string $currency, ForecastView $view, int $months): ?string
    {
        if ($wanted->isEmpty()) {
            return null;
        }

        $context = $this->planner->context($user, $currency, $view, $months);
        $lines = $this->planner->lines($user, $currency, $context, $wanted->map(fn (WishlistItem $item) => ['item_id' => $item->id])->values()->all());
        $best = $this->planner->suggest($context, $lines, 1)[0] ?? null;

        if ($best === null) {
            return null;
        }

        $dates = array_column($best['lines'], 'purchaseDate');

        return in_array(null, $dates, true) ? null : max($dates);
    }

    /**
     * @return Collection<int, Bucket>
     */
    private function buckets(User $user): Collection
    {
        return Bucket::forUser($user)->inPriorityOrder()->get();
    }
}
