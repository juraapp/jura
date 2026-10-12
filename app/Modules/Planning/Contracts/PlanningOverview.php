<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\ForecastView;

/**
 * Read-only summaries of buckets and the wish list for reports.
 */
interface PlanningOverview
{
    /**
     * Buckets with their funded balance, per currency.
     *
     * @return array<string, list<array{id: int, name: string, kind: string, isDefault: bool, balance: float, available: float, cap: float|null}>>
     */
    public function bucketsByCurrency(User $user): array;

    /**
     * Net allocation movement per bucket per month for the last N months (oldest first), per currency.
     *
     * @return array<string, array{labels: list<string>, series: list<array{name: string, data: list<float>}>}>
     */
    public function allocationHistory(User $user, int $months): array;

    /**
     * Each bucket's available funds today and projected at the end of the horizon, per currency.
     *
     * @return array<string, list<array{id: int, name: string, now: float, projected: float}>>
     */
    public function projectedBucketBalances(User $user, ForecastView $view, int $months): array;

    /**
     * Wish-list totals per project (and unassigned items) per currency.
     *
     * @return list<array<string, mixed>>
     */
    public function projectSummaries(User $user, ForecastView $view, int $months): array;
}
