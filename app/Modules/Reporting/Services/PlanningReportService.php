<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Contracts\CashFlowForecast;
use App\Modules\Planning\Contracts\PlanningOverview;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Report data that sets real history beside projections. Recorded figures
 * (ReportService) and projected figures (Planning) are kept in separate keys so
 * a page can never mistake one for the other.
 */
class PlanningReportService
{
    /** How many months of recorded history and of projection the forecast report shows. */
    public const HISTORY_MONTHS = 6;

    public const PROJECTION_MONTHS = 6;

    public function __construct(
        private readonly ReportService $reports,
        private readonly CashFlowForecast $forecast,
        private readonly PlanningOverview $overview,
    ) {}

    /**
     * @return list<array{currency: string, opening: float, actual: list<array{label: string, netWorth: float}>, projected: array<string, list<array<string, mixed>>>}>
     */
    public function forecast(User $user): array
    {
        $actual = $this->reports->netWorthEvolution($user, self::HISTORY_MONTHS);

        $projections = [];
        foreach (ForecastView::cases() as $view) {
            // The first month is the current one; it is already part of the recorded history above.
            $projections[$view->value] = $this->forecast->monthly($user, $view, self::PROJECTION_MONTHS + 1);
        }

        $currencies = $actual->keys()
            ->merge($projections[ForecastView::Expected->value]->keys())
            ->unique()
            ->sort()
            ->values();

        return $currencies->map(function (string $currency) use ($actual, $projections) {
            $expected = $projections[ForecastView::Expected->value]->get($currency);

            return [
                'currency' => $currency,
                'opening' => ($expected['opening'] ?? Money::zero())->toFloat(),
                'actual' => $actual->get($currency, collect())->map(fn (array $row) => [
                    'label' => $row['label'],
                    'netWorth' => $row['netWorth']->toFloat(),
                ])->values()->all(),
                'projected' => collect($projections)->map(fn ($byCurrency) => collect($byCurrency->get($currency)['months'] ?? [])
                    ->slice(1)
                    ->map(fn (array $month) => [
                        'month' => $month['month'],
                        'label' => Carbon::createFromFormat('Y-m-d', $month['month'].'-01')->translatedFormat('M Y'),
                        'income' => $month['income']->toFloat(),
                        'expense' => $month['expense']->toFloat(),
                        'interest' => $month['interest']->toFloat(),
                        'net' => $month['net']->toFloat(),
                        'closing' => $month['closing']->toFloat(),
                    ])
                    ->values()
                    ->all())->all(),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buckets(User $user, ForecastView $view): array
    {
        $buckets = $this->overview->bucketsByCurrency($user);
        $history = $this->overview->allocationHistory($user, 6);
        $projected = $this->overview->projectedBucketBalances($user, $view, 12);

        return collect(array_keys($buckets))->map(fn (string $currency) => [
            'currency' => $currency,
            'buckets' => $buckets[$currency],
            'history' => $history[$currency] ?? ['labels' => [], 'series' => []],
            'projected' => $projected[$currency] ?? [],
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function projects(User $user, ForecastView $view): array
    {
        return $this->overview->projectSummaries($user, $view, 24);
    }

    /**
     * Planned money over the next N days, per currency (expected view).
     *
     * @return list<array{currency: string, income: float, expense: float, net: float}>
     */
    public function upcoming(User $user, int $days = 30): array
    {
        $window = $this->forecast->window($user, ForecastView::Expected, today(), today()->addDays($days));

        return collect($window)->map(fn (array $totals, string $currency) => [
            'currency' => $currency,
            'income' => $totals['income']->toFloat(),
            'expense' => $totals['expense']->toFloat(),
            'net' => $totals['income']->subtract($totals['expense'])->toFloat(),
        ])->values()->all();
    }
}
