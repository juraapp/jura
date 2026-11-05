<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Projected (never recorded) cash flow built from planned events.
 */
interface CashFlowForecast
{
    /**
     * Month-by-month projected balances per currency, starting with the current month.
     *
     * @return Collection<string, array{currency: string, opening: Money, months: list<array{month: string, income: Money, expense: Money, interest: Money, net: Money, closing: Money}>}>
     */
    public function monthly(User $user, ForecastView $view, int $months): Collection;

    /**
     * Month-by-month projected cash in on-budget accounts per currency, starting with the current month: today's
     * balance of the active on-budget accounts, the planned income the view trusts and the planned expenses in
     * those accounts (or without an account), and planned transfers that cross the budget's edge (boundary: + into
     * the budget, - out of it). Projected interest is left out.
     *
     * @return array<string, array{opening: Money, months: list<array{month: string, income: Money, expense: Money, boundary: Money, closing: Money}>}>
     */
    public function budgetMonthly(User $user, ForecastView $view, int $months): array;

    /**
     * Planned money between two dates, per currency. Overdue open events count as today.
     *
     * @return array<string, array{income: Money, expense: Money}>
     */
    public function window(User $user, ForecastView $view, CarbonInterface $from, CarbonInterface $to): array;
}
