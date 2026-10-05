<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Turns the numbers ReportService already computed into short Spanish
 * sentences. Every phrase is a template filled with real aggregates —
 * never freeform generated text — so nothing here can be "made up".
 */
class InsightService
{
    /** Gastos individuales por debajo de este monto cuentan como "hormiga". */
    private const ANT_EXPENSE_THRESHOLD = 30000;

    /** Un gasto se marca inusual si supera la media histórica + N desviaciones. */
    private const UNUSUAL_STDDEV_MULTIPLIER = 1.5;

    /**
     * @return array<int, string>
     */
    public function monthlyHighlights(array $summary, ?User $user = null, ?int $year = null, ?int $month = null): array
    {
        $lines = [];
        $currency = $summary['currency'] ?? $user?->currency_default ?? 'COP';

        if (! $summary['income']->isZero() || ! $summary['expense']->isZero()) {
            $lines[] = $this->expenseVsPreviousMonth($summary);
        }

        if ($summary['topCategory']) {
            $lines[] = __(':category accounted for :percentage% of your expenses this month.', [
                'category' => $summary['topCategory']['category']?->name ?? __('A category'),
                'percentage' => number_format($summary['topCategory']['percentage'], 1),
            ]);
        }

        if ($summary['savings']->isPositive() && ! $summary['income']->isZero()) {
            $lines[] = __('You saved :amount this month, equivalent to :rate% of your income.', [
                'amount' => $summary['savings']->format($currency),
                'rate' => number_format($summary['savingsRate'], 1),
            ]);
        } elseif ($summary['savings']->isNegative()) {
            $lines[] = __('You spent :amount more than you earned this month.', [
                'amount' => $summary['savings']->abs()->format($currency),
            ]);
        }

        if ($summary['daysOfAutonomy'] !== null) {
            $lines[] = __('At your current spending pace, your available money will last :days days without new income.', [
                'days' => number_format($summary['daysOfAutonomy'], 0),
            ]);
        }

        if ($user && $year && $month) {
            $ants = $this->antExpenses($user, $year, $month, $currency, $summary['expense']);
            if ($ants && $ants['percentageOfExpense'] >= 5) {
                $lines[] = __('Your small purchases (under $:threshold) added up to :amount this month across :count :unit — :percentage% of your total spending.', [
                    'threshold' => number_format(self::ANT_EXPENSE_THRESHOLD, 0, ',', '.'),
                    'amount' => $ants['total']->format($currency),
                    'count' => $ants['count'],
                    'unit' => $ants['count'] === 1 ? __('transaction') : __('transactions'),
                    'percentage' => number_format($ants['percentageOfExpense'], 1),
                ]);
            }

            $unusual = $this->unusualExpenses($user, $year, $month, $currency);
            if ($unusual->isNotEmpty()) {
                $top = $unusual->sortByDesc(fn (Transaction $t) => $t->amount->toFloat())->first();
                $lines[] = __('We detected an unusual expense: :description (:amount) is well above your usual average in :category.', [
                    'description' => $top->description ?: $top->category?->name,
                    'amount' => $top->amount->format($currency),
                    'category' => $top->category?->name ?? __('that category'),
                ]);
            }
        }

        return $lines;
    }

    /**
     * Gastos hormiga: la suma de compras pequeñas y frecuentes que, juntas,
     * suelen pesar más de lo que el usuario percibe.
     *
     * @return array{total: Money, count: int, percentageOfExpense: float}|null
     */
    public function antExpenses(User $user, int $year, int $month, string $currency, Money $totalExpense): ?array
    {
        $rows = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->where('accounts.currency', $currency)
            ->whereYear('transactions.date', $year)->whereMonth('transactions.date', $month)
            ->where('transactions.amount', '<', self::ANT_EXPENSE_THRESHOLD)
            ->select('transactions.*')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $total = $rows->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero());

        return [
            'total' => $total,
            'count' => $rows->count(),
            'percentageOfExpense' => $total->percentageOf($totalExpense),
        ];
    }

    /**
     * Gastos que se salen notablemente del patrón histórico de su categoría
     * (más de N desviaciones estándar sobre la media de los últimos 6 meses,
     * excluyendo el mes actual). No requiere ML: es media + desviación simple.
     *
     * @return Collection<int, Transaction>
     */
    public function unusualExpenses(User $user, int $year, int $month, string $currency): Collection
    {
        $periodStart = now()->createFromDate($year, $month, 1)->subMonths(6)->startOfMonth();
        $periodEnd = now()->createFromDate($year, $month, 1)->startOfMonth();

        $stats = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->where('accounts.currency', $currency)
            ->whereBetween('transactions.date', [$periodStart, $periodEnd])
            ->selectRaw('transactions.category_id as category_id, AVG(transactions.amount) as avg_amount, STDDEV(transactions.amount) as stddev_amount, COUNT(*) as n')
            ->groupBy('transactions.category_id')
            ->having('n', '>=', 3)
            ->get()
            ->keyBy('category_id');

        if ($stats->isEmpty()) {
            return collect();
        }

        return Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->with('category')
            ->where('transactions.type', TransactionType::Expense->value)
            ->where('accounts.currency', $currency)
            ->whereYear('transactions.date', $year)->whereMonth('transactions.date', $month)
            ->whereIn('transactions.category_id', $stats->keys())
            ->select('transactions.*')
            ->get()
            ->filter(function (Transaction $transaction) use ($stats) {
                $stat = $stats->get($transaction->category_id);
                $stddev = (float) ($stat->stddev_amount ?? 0);
                $threshold = (float) $stat->avg_amount + (self::UNUSUAL_STDDEV_MULTIPLIER * max($stddev, (float) $stat->avg_amount * 0.2));

                return $transaction->amount->toFloat() > $threshold;
            })
            ->values();
    }

    private function expenseVsPreviousMonth(array $summary): string
    {
        $change = $summary['changeExpense'];

        if (abs($change) < 1) {
            return __('Your expenses stayed about the same as last month.');
        }

        return __('This month you spent :percentage% :direction than last month.', [
            'percentage' => number_format(abs($change), 1),
            'direction' => $change > 0 ? __('more') : __('less'),
        ]);
    }
}
