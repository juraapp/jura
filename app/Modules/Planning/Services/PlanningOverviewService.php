<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Contracts\PlanningOverview;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
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
        private readonly ProjectOverview $projects,
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
                'balance' => $this->funds->balance($bucket)->toFloat(),
                'available' => $this->funds->available($bucket)->toFloat(),
                'cap' => $bucket->cap_amount?->toFloat(),
            ])->values()->all())
            ->all();
    }

    public function allocationHistory(User $user, int $months): array
    {
        $start = Carbon::instance($user->today())->startOfMonth()->subMonths($months - 1);
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
        return $this->projects->index($user, $view, $months);
    }

    /**
     * @return Collection<int, Bucket>
     */
    private function buckets(User $user): Collection
    {
        return Bucket::forUser($user)->inPriorityOrder()->get();
    }
}
