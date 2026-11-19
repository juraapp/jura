<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Contracts\SavingsTargets;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Planning\Support\SavingsPace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SavingsTargetService implements SavingsTargets
{
    /** The forecast never looks further than this, however late a target date is. */
    private const MAX_LOOKAHEAD_MONTHS = 36;

    public function __construct(
        private readonly BucketLedger $funds,
        private readonly ForecastInputBuilder $builder,
        private readonly ForecastEngine $engine,
        private readonly SavingsPace $pace,
    ) {}

    public function targets(User $user): array
    {
        $today = CarbonImmutable::instance($user->today());

        $buckets = Bucket::forUser($user)
            ->where('kind', BucketKind::Savings->value)
            ->whereNotNull('target_date')
            ->whereNotNull('cap_amount')
            ->whereNull('completed_on')
            ->orderBy('target_date')
            ->get();

        if ($buckets->isEmpty()) {
            return [];
        }

        $planned = $this->plannedBucketIds($user);
        $driven = $buckets->filter(fn (Bucket $bucket) => $bucket->fill_rule !== null || in_array($bucket->id, $planned, true));
        $reached = $this->reachedDates($user, $driven, $today);

        return $buckets->map(function (Bucket $bucket) use ($today, $driven, $reached) {
            $target = CarbonImmutable::parse($bucket->target_date->toDateString());
            $envelope = $this->funds->envelope($bucket);
            $pace = $this->pace->status($bucket->cap_amount, $envelope, CarbonImmutable::parse($bucket->created_at->toDateString()), $target, $today);

            $isDriven = $driven->contains('id', $bucket->id);
            $projectedOn = $isDriven ? ($reached[$bucket->id] ?? null) : null;
            $behind = $isDriven
                ? $envelope->lessThan($bucket->cap_amount) && ($projectedOn === null || $projectedOn > $target->toDateString())
                : $pace['behind'];

            return [
                'bucketId' => $bucket->id,
                'name' => $bucket->name,
                'currency' => $bucket->currency,
                'targetDate' => $target->toDateString(),
                'overdue' => $target->lte($today),
                'percent' => round($pace['percent'], 1),
                'behind' => $behind,
                'projectedOn' => $projectedOn,
                'neededPerMonth' => $pace['neededPerMonth']->toFloat(),
                'basis' => $isDriven ? 'plan' : 'pace',
            ];
        })->values()->all();
    }

    /**
     * The buckets the user's open planned incomes put money into explicitly.
     *
     * @return list<int>
     */
    private function plannedBucketIds(User $user): array
    {
        return PlannedEvent::forUser($user)
            ->where('status', PlannedEventStatus::Planned->value)
            ->whereNotNull('allocation_overrides')
            ->pluck('allocation_overrides')
            ->flatten(1)
            ->pluck('bucket_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The day each of these buckets first holds its target in the forecast (one run per currency).
     *
     * @param  Collection<int, Bucket>  $buckets
     * @return array<int, string> bucket id => date
     */
    private function reachedDates(User $user, Collection $buckets, CarbonImmutable $today): array
    {
        $dates = [];
        $horizon = $today->addMonths(self::MAX_LOOKAHEAD_MONTHS);

        foreach ($buckets->groupBy('currency') as $currency => $inCurrency) {
            $latest = CarbonImmutable::parse($inCurrency->max(fn (Bucket $bucket) => $bucket->target_date->toDateString()));
            $until = $latest->gt($today->addMonths(12)) ? $latest : $today->addMonths(12);
            $until = $until->gt($horizon) ? $horizon : $until;

            $inputs = $this->builder->build($user, (string) $currency, ForecastView::default(), $until);
            $result = $this->engine->run(new EngineInput($inputs->start, $until, $inputs->startCash, $inputs->buckets, $inputs->flows, $inputs->timeline), []);

            foreach ($inCurrency as $bucket) {
                if (isset($result->reached[$bucket->id])) {
                    $dates[$bucket->id] = $result->reached[$bucket->id]->toDateString();
                }
            }
        }

        return $dates;
    }
}
