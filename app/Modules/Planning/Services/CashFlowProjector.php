<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Contracts\CashFlowForecast;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Projects account balances month by month from open planned events. Purely
 * projected data: nothing here reads or writes real transactions, except the
 * opening balance, which comes from the ledger's current net worth.
 */
class CashFlowProjector implements CashFlowForecast
{
    public function __construct(private readonly Balances $balances) {}

    /**
     * @return Collection<string, array{currency: string, opening: Money, months: list<array{month: string, income: Money, expense: Money, net: Money, closing: Money}>}>
     */
    public function monthly(User $user, ForecastView $view, int $months): Collection
    {
        return $this->project($user, $view, $months);
    }

    public function window(User $user, ForecastView $view, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->endOfDay();

        $totals = [];
        PlannedEvent::forUser($user)->open()->where('date', '<=', $to->toDateString())->get()
            ->each(function (PlannedEvent $event) use (&$totals, $from, $to, $view) {
                $date = $event->date->lt($from) ? $from : $event->date;
                if ($date->gt($to)) {
                    return;
                }

                $totals[$event->currency] ??= ['income' => Money::zero(), 'expense' => Money::zero()];

                if ($event->type === TransactionType::Income) {
                    if ($view->includesIncome($event->certainty)) {
                        $totals[$event->currency]['income'] = $totals[$event->currency]['income']->add($event->amount);
                    }
                } else {
                    $totals[$event->currency]['expense'] = $totals[$event->currency]['expense']->add($event->amount);
                }
            });

        ksort($totals);

        return $totals;
    }

    public function project(User $user, ForecastView $view, int $months = 6, ?CarbonInterface $today = null): Collection
    {
        $today = Carbon::instance($today ?? now())->startOfDay();
        $firstMonth = $today->copy()->startOfMonth();
        $horizonEnd = $firstMonth->copy()->addMonthsNoOverflow($months)->subDay()->endOfDay();

        $events = PlannedEvent::forUser($user)
            ->open()
            ->where('date', '<=', $horizonEnd->toDateString())
            ->get();

        $opening = $this->balances->netWorthByCurrency($user);
        $currencies = $opening->keys()->merge($events->pluck('currency'))->unique()->sort()->values();

        return $currencies->mapWithKeys(function (string $currency) use ($events, $opening, $view, $months, $firstMonth, $today) {
            $running = $opening->get($currency, Money::zero());
            $currencyEvents = $events->where('currency', $currency);

            $series = [];
            for ($i = 0; $i < $months; $i++) {
                $monthStart = $firstMonth->copy()->addMonthsNoOverflow($i);
                $income = Money::zero();
                $expense = Money::zero();

                foreach ($currencyEvents as $event) {
                    if ($this->bucketMonth($event, $today)->ne($monthStart)) {
                        continue;
                    }

                    if ($event->type === TransactionType::Income) {
                        if ($view->includesIncome($event->certainty)) {
                            $income = $income->add($event->amount);
                        }
                    } else {
                        $expense = $expense->add($event->amount);
                    }
                }

                $net = $income->subtract($expense);
                $running = $running->add($net);

                $series[] = [
                    'month' => $monthStart->format('Y-m'),
                    'income' => $income,
                    'expense' => $expense,
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
     * Overdue events that are still open are treated as happening this month.
     */
    private function bucketMonth(PlannedEvent $event, Carbon $today): Carbon
    {
        $date = $event->date->lt($today) ? $today : $event->date;

        return $date->copy()->startOfMonth();
    }
}
