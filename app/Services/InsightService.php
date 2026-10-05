<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Turns the numbers ReportService already computed into short sentences.
 * Every phrase is a template filled with real aggregates — never freeform
 * generated text — so nothing here can be "made up".
 */
class InsightService
{
    /** Individual expenses below this amount count as "ant expenses" (small, frequent purchases). */
    private const ANT_EXPENSE_THRESHOLD = 30000;

    /** An expense is flagged as unusual if it exceeds the historical mean plus N standard deviations. */
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
            $lines[] = __('insights.top_category', [
                'category' => $summary['topCategory']['category']?->name ?? __('insights.default_category'),
                'percentage' => number_format($summary['topCategory']['percentage'], 1),
            ]);
        }

        if ($summary['savings']->isPositive() && ! $summary['income']->isZero()) {
            $lines[] = __('insights.savings_positive', [
                'amount' => $summary['savings']->format($currency),
                'percentage' => number_format($summary['savingsRate'], 1),
            ]);
        } elseif ($summary['savings']->isNegative()) {
            $lines[] = __('insights.savings_negative', [
                'amount' => $summary['savings']->abs()->format($currency),
            ]);
        }

        if ($summary['daysOfAutonomy'] !== null) {
            $lines[] = __('insights.days_of_autonomy', [
                'days' => number_format($summary['daysOfAutonomy'], 0),
            ]);
        }

        if ($user && $year && $month) {
            $ants = $this->antExpenses($user, $year, $month, $currency, $summary['expense']);
            if ($ants && $ants['percentageOfExpense'] >= 5) {
                $lines[] = __('insights.ant_expenses', [
                    'threshold' => number_format(self::ANT_EXPENSE_THRESHOLD, 0, ',', '.'),
                    'total' => $ants['total']->format($currency),
                    'movement' => trans_choice('insights.movement', $ants['count']),
                    'percentage' => number_format($ants['percentageOfExpense'], 1),
                ]);
            }

            $unusual = $this->unusualExpenses($user, $year, $month, $currency);
            if ($unusual->isNotEmpty()) {
                $top = $unusual->sortByDesc(fn (Transaction $t) => $t->amount->toFloat())->first();
                $lines[] = __('insights.unusual_expense', [
                    'description' => $top->description ?: $top->category?->name,
                    'amount' => $top->amount->format($currency),
                    'category' => $top->category?->name ?? __('insights.that_category'),
                ]);
            }
        }

        return $lines;
    }

    /**
     * "Ant expenses": the sum of small, frequent purchases that, together,
     * usually weigh more than the user perceives.
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
     * Expenses that stand out notably from their category's historical
     * pattern (more than N standard deviations above the mean of the last
     * 6 months, excluding the current month). No ML required: mean + simple
     * standard deviation.
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
            return __('insights.expense_vs_previous_month_flat');
        }

        return __('insights.expense_vs_previous_month', [
            'percentage' => number_format(abs($change), 1),
            'direction' => __($change > 0 ? 'insights.more' : 'insights.less'),
        ]);
    }
}
