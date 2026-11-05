<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Support\CyclePeriod;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\EngineResult;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * What each bucket's current cycle (or calendar month, for a bucket without one) is expected to look like, from the
 * forecast engine: the provisional fill from planned income still to come (never stored, never touches a balance),
 * what it holds at the end, what it gives away when the cycle closes and what other buckets pass to it. Also the money
 * funded envelopes are projected to hold on given days.
 */
class BucketProjection
{
    public function __construct(
        private readonly ForecastInputBuilder $builder,
        private readonly ForecastEngine $engine,
    ) {}

    /**
     * @return array<int, array{start: Carbon, end: Carbon, available: Money, provisional: Money, projectedEnd: Money, projectedRelease: Money, projectedIn: Money, projectedClose: Money}>
     *                                                                                                                                                                                      keyed by bucket id
     */
    public function project(User $user, string $currency, ?ForecastView $view = null): array
    {
        $today = CarbonImmutable::instance($user->today())->startOfDay();
        $horizon = $today;
        foreach (Bucket::forUser($user)->where('currency', $currency)->get() as $bucket) {
            $period = $bucket->cyclePeriod() ?? new CyclePeriod(BudgetPeriodType::Monthly);
            $end = CarbonImmutable::instance($period->endOf($today->toDateTimeImmutable()));
            $horizon = $end->gt($horizon) ? $end : $horizon;
        }

        [$input, $result] = $this->run($user, $currency, $view ?? ForecastView::default(), $horizon);

        $projection = [];
        foreach ($input->buckets as $bucket) {
            $cycle = $result->cycles[$bucket->id] ?? null;
            if ($cycle === null) {
                continue;
            }

            $projection[$bucket->id] = [
                'start' => Carbon::parse($cycle->start->toDateString()),
                'end' => Carbon::parse($cycle->end->toDateString()),
                'available' => $bucket->envelope,
                'provisional' => $cycle->provisional,
                'projectedEnd' => $cycle->projectedEnd,
                'projectedRelease' => $cycle->released,
                'projectedIn' => $cycle->receivedFromOthers,
                'projectedClose' => $cycle->close,
            ];
        }

        return $projection;
    }

    /**
     * The money funded envelopes of a currency are projected to hold at the end of each day.
     *
     * @param  list<CarbonInterface>  $dates
     * @return array<string, Money> keyed by Y-m-d
     */
    public function earmarksAt(User $user, string $currency, ForecastView $view, array $dates): array
    {
        $today = CarbonImmutable::instance($user->today())->startOfDay();
        $last = $today;
        foreach ($dates as $date) {
            $last = $date->gt($last) ? CarbonImmutable::instance($date)->startOfDay() : $last;
        }

        [$input, $result] = $this->run($user, $currency, $view, $last);
        $startEarmarked = $input->startCash->subtract($input->startReadyToAssign());

        $earmarks = [];
        foreach ($dates as $date) {
            $point = $result->pointAt(CarbonImmutable::instance($date)->startOfDay());
            $earmarks[$date->format('Y-m-d')] = $point?->earmarked ?? $startEarmarked;
        }

        return $earmarks;
    }

    /**
     * @return array{0: EngineInput, 1: EngineResult}
     */
    private function run(User $user, string $currency, ForecastView $view, CarbonImmutable $until): array
    {
        $inputs = $this->builder->build($user, $currency, $view, $until);
        $input = new EngineInput($inputs->start, $until, $inputs->startCash, $inputs->buckets, $inputs->flows, $inputs->timeline);

        return [$input, $this->engine->run($input, [])];
    }
}
