<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Planning\Contracts\CashFlowForecast;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Support\ProjectedItem;
use App\Modules\Planning\Support\YieldSimulator;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Projects account balances month by month from open planned money (PlannedOccurrences). Purely
 * projected data: nothing here reads or writes real transactions, except the
 * opening balance, which comes from the ledger's current net worth.
 */
class CashFlowProjector implements CashFlowForecast
{
    public function __construct(
        private readonly Balances $balances,
        private readonly YieldSimulator $yield,
        private readonly PlannedOccurrences $occurrences,
    ) {}

    /**
     * @return Collection<string, array{currency: string, opening: Money, months: list<array{month: string, income: Money, expense: Money, interest: Money, net: Money, closing: Money}>}>
     */
    public function monthly(User $user, ForecastView $view, int $months): Collection
    {
        return $this->project($user, $view, $months);
    }

    public function window(User $user, ForecastView $view, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->startOfDay();

        $totals = [];
        foreach ($this->occurrences->open($user, $to) as $item) {
            $date = $item->date->lt($from) ? $from : $item->date;
            if ($date->gt($to)) {
                continue;
            }

            $totals[$item->currency] ??= ['income' => Money::zero(), 'expense' => Money::zero()];

            if ($item->isIncome() && $view->includesIncome($item->certainty)) {
                $totals[$item->currency]['income'] = $totals[$item->currency]['income']->add($item->amount);
            } elseif ($item->isExpense()) {
                $totals[$item->currency]['expense'] = $totals[$item->currency]['expense']->add($item->amount);
            }
        }

        ksort($totals);

        return $totals;
    }

    public function project(User $user, ForecastView $view, int $months = 6, ?CarbonInterface $today = null): Collection
    {
        $today = CarbonImmutable::instance($today ?? $user->today())->startOfDay();
        $firstMonth = $today->startOfMonth();
        $horizonEnd = $firstMonth->addMonthsNoOverflow($months)->subDay();

        $items = $this->occurrences->open($user, $horizonEnd);

        $opening = $this->balances->netWorthByCurrency($user);
        $interest = $this->interestByMonth($user, $view, $items, $today, $horizonEnd);
        $currencies = $opening->keys()->merge($items->pluck('currency'))->unique()->sort()->values();

        return $currencies->mapWithKeys(function (string $currency) use ($items, $opening, $view, $months, $firstMonth, $today, $interest) {
            $running = $opening->get($currency, Money::zero());
            $byMonth = [];
            foreach ($items->where('currency', $currency) as $item) {
                $month = $item->effectiveDate($today)->format('Y-m');
                $byMonth[$month] ??= ['income' => Money::zero(), 'expense' => Money::zero()];

                if ($item->isIncome() && $view->includesIncome($item->certainty)) {
                    $byMonth[$month]['income'] = $byMonth[$month]['income']->add($item->amount);
                } elseif ($item->isExpense()) {
                    $byMonth[$month]['expense'] = $byMonth[$month]['expense']->add($item->amount);
                }
            }

            $series = [];
            for ($i = 0; $i < $months; $i++) {
                $month = $firstMonth->addMonthsNoOverflow($i)->format('Y-m');
                $income = $byMonth[$month]['income'] ?? Money::zero();
                $expense = $byMonth[$month]['expense'] ?? Money::zero();
                $earned = $interest[$currency][$month] ?? Money::zero();
                $net = $income->subtract($expense)->add($earned);
                $running = $running->add($net);

                $series[] = [
                    'month' => $month,
                    'income' => $income,
                    'expense' => $expense,
                    'interest' => $earned,
                    'net' => $net,
                    'closing' => $running,
                ];
            }

            return [$currency => [
                'currency' => $currency,
                'opening' => $opening->get($currency, Money::zero()),
                'months' => $series,
            ]];
        });
    }

    /**
     * Interest the yield-bearing accounts are projected to earn, by currency and month, with the
     * open planned money that hits each account moving its balance on its date.
     *
     * @param  Collection<int, ProjectedItem>  $items
     * @return array<string, array<string, Money>>
     */
    private function interestByMonth(User $user, ForecastView $view, Collection $items, CarbonImmutable $today, CarbonImmutable $horizonEnd): array
    {
        $totals = [];

        foreach ($this->balances->yieldBearing($user) as $account) {
            $flows = [];
            foreach ($items->where('accountId', $account['id']) as $item) {
                if (($item->isIncome() && ! $view->includesIncome($item->certainty)) || (! $item->isIncome() && ! $item->isExpense())) {
                    continue;
                }

                $date = $item->effectiveDate($today)->toDateString();
                $flows[$date] = ($flows[$date] ?? Money::zero())->add($item->isIncome() ? $item->amount : $item->amount->negate());
            }

            $months = $this->yield->run($account['balance'], $account['rate'], $account['compounding'], $today, $horizonEnd->endOfDay(), $flows);

            foreach ($months as $month => $row) {
                $totals[$account['currency']][$month] = ($totals[$account['currency']][$month] ?? Money::zero())->add($row['interest']);
            }
        }

        return $totals;
    }
}
