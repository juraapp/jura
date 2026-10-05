<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the raw transaction ledger into the aggregates the dashboard and
 * reports need. Every number here is derived on read (no stored snapshots
 * outside monthly_reports, added in Fase 5) directly from `transactions`.
 *
 * Amounts in different currencies are never added together — every
 * aggregate below is grouped by currency (via the owning account) and
 * returned as a Collection keyed by currency code.
 */
class ReportService
{
    public function __construct(private readonly BalanceCalculator $balances) {}

    /**
     * Currencies the user actually has accounts in, so aggregates only
     * report on currencies that are meaningful for them.
     *
     * @return Collection<int, string>
     */
    public function currenciesForUser(User $user): Collection
    {
        $currencies = $user->accounts()->pluck('currency')->unique()->sort()->values();

        return $currencies->isEmpty() ? collect([$user->currency_default]) : $currencies;
    }

    /**
     * @return Collection<string, array{
     *   income: Money, expense: Money, savings: Money, savingsRate: float,
     *   avgDailyExpense: Money, biggestExpense: ?Transaction, transactionCount: int,
     *   changeIncome: float, changeExpense: float, changeSavings: float,
     *   changeSavingsRate: float, changeTransactionCount: float,
     *   netWorth: Money, daysOfAutonomy: ?float, topCategory: ?array,
     * }>
     */
    public function monthlySummary(User $user, int $year, int $month): Collection
    {
        $current = $this->periodTotals($user, $year, $month);
        $previousDate = Carbon::create($year, $month, 1)->subMonthNoOverflow();
        $previous = $this->periodTotals($user, $previousDate->year, $previousDate->month);
        $netWorthByCurrency = $this->balances->netWorthByCurrency($user);

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $daysElapsed = min(
            $daysInMonth,
            Carbon::create($year, $month, 1)->isSameMonth(now()) ? now()->day : $daysInMonth,
        );

        return $current->mapWithKeys(function (array $totals, string $currency) use ($previous, $netWorthByCurrency, $daysElapsed) {
            $previousTotals = $previous->get($currency, $this->emptyPeriodTotals());
            $netWorth = $netWorthByCurrency->get($currency, Money::zero());

            $avgDailyExpense = $daysElapsed > 0 ? $totals['expense']->divide($daysElapsed) : Money::zero();
            $liquidExpenseAvg = $avgDailyExpense->isPositive() ? $avgDailyExpense : $this->trailingDailyExpenseAverage($currency);

            return [$currency => [
                'currency' => $currency,
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                'savings' => $totals['savings'],
                'savingsRate' => $totals['savingsRate'],
                'avgDailyExpense' => $avgDailyExpense,
                'biggestExpense' => $totals['biggestExpense'],
                'transactionCount' => $totals['transactionCount'],
                'netWorth' => $netWorth,
                'daysOfAutonomy' => $liquidExpenseAvg->isPositive() ? $netWorth->divide($liquidExpenseAvg->toDecimalString())->toFloat() : null,
                'topCategory' => $totals['topCategory'],
                'changeIncome' => $this->percentChange($previousTotals['income'], $totals['income']),
                'changeExpense' => $this->percentChange($previousTotals['expense'], $totals['expense']),
                'changeSavings' => $this->percentChange($previousTotals['savings'], $totals['savings']),
                'changeSavingsRate' => $totals['savingsRate'] - $previousTotals['savingsRate'],
                'changeTransactionCount' => $previousTotals['transactionCount'] > 0
                    ? (($totals['transactionCount'] - $previousTotals['transactionCount']) / $previousTotals['transactionCount']) * 100
                    : 0.0,
            ]];
        });
    }

    /**
     * @return Collection<string, Collection<int, array{category: Category, total: Money, percentage: float}>>
     */
    public function expenseByCategory(User $user, int $year, int $month): Collection
    {
        $rows = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->selectRaw('transactions.category_id as category_id, accounts.currency as currency, SUM(transactions.amount) as total')
            ->groupBy('transactions.category_id', 'accounts.currency')
            ->get();

        $categories = Category::withoutGlobalScopes()->whereIn('id', $rows->pluck('category_id'))->get()->keyBy('id');

        return $this->currenciesForUser($user)->mapWithKeys(function (string $currency) use ($rows, $categories) {
            $currencyRows = $rows->where('currency', $currency);
            $totalExpense = $currencyRows->reduce(fn (Money $c, $row) => $c->add(Money::of($row->total)), Money::zero());

            $mapped = $currencyRows->map(function ($row) use ($categories, $totalExpense) {
                $total = Money::of($row->total);

                return [
                    'category' => $categories->get($row->category_id),
                    'total' => $total,
                    'percentage' => $total->percentageOf($totalExpense),
                ];
            })->sortByDesc(fn ($row) => $row['total']->toFloat())->values();

            return [$currency => $mapped];
        });
    }

    /**
     * @return Collection<int, array{account: Account, total: Money}>
     */
    public function expenseByAccount(User $user, int $year, int $month): Collection
    {
        $rows = Transaction::query()
            ->where('type', TransactionType::Expense->value)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->orderByDesc('total')
            ->get();

        $accounts = Account::withoutGlobalScopes()->whereIn('id', $rows->pluck('account_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'account' => $accounts->get($row->account_id),
            'total' => Money::of($row->total),
        ])->filter(fn ($row) => $row['account'] !== null)->values();
    }

    /**
     * @return Collection<int, array{account: Account, balance: Money}>
     */
    public function moneyDistribution(User $user): Collection
    {
        return $user->accounts()->where('is_active', true)->get()
            ->map(fn (Account $account) => [
                'account' => $account,
                'balance' => $this->balances->accountBalance($account),
            ])
            ->filter(fn ($row) => $row['balance']->isPositive());
    }

    /**
     * @return Collection<string, Collection<int, array{label: string, income: Money, expense: Money, savings: Money}>>
     */
    public function monthlyEvolution(User $user, int $months = 12): Collection
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->whereIn('transactions.type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->where('transactions.date', '>=', $start)
            ->selectRaw("DATE_FORMAT(transactions.date, '%Y-%m') as ym, transactions.type as type, accounts.currency as currency, SUM(transactions.amount) as total")
            ->groupBy('ym', 'type', 'currency')
            ->get()
            ->groupBy('currency');

        return $this->currenciesForUser($user)->mapWithKeys(function (string $currency) use ($start, $months, $rows) {
            $byMonth = ($rows->get($currency) ?? collect())->groupBy('ym');

            $series = collect(range(0, $months - 1))->map(function ($i) use ($start, $byMonth) {
                $date = $start->copy()->addMonths($i);
                $ym = $date->format('Y-m');
                $bucket = $byMonth->get($ym, collect());

                $income = Money::of($bucket->firstWhere('type', TransactionType::Income->value)->total ?? 0);
                $expense = Money::of($bucket->firstWhere('type', TransactionType::Expense->value)->total ?? 0);

                return [
                    'label' => $date->translatedFormat('M Y'),
                    'income' => $income,
                    'expense' => $expense,
                    'savings' => $income->subtract($expense),
                ];
            });

            return [$currency => $series];
        });
    }

    /**
     * @return Collection<string, array{
     *   income: Money, expense: Money, savings: Money, savingsRate: float,
     *   avgMonthlyIncome: Money, avgMonthlyExpense: Money,
     *   bestIncomeMonth: ?array, worstExpenseMonth: ?array, topCategory: ?array,
     *   months: Collection,
     * }>
     */
    public function annualSummary(User $user, int $year): Collection
    {
        $rows = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->whereYear('transactions.date', $year)
            ->whereIn('transactions.type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->select('transactions.*', 'accounts.currency as account_currency')
            ->get();

        return $this->currenciesForUser($user)->mapWithKeys(function (string $currency) use ($rows, $year) {
            $currencyRows = $rows->where('account_currency', $currency);

            $months = collect(range(1, 12))->map(function ($month) use ($currencyRows, $year) {
                $monthRows = $currencyRows->filter(fn (Transaction $t) => $t->date->month === $month);
                $income = $monthRows->where('type', TransactionType::Income)->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero());
                $expense = $monthRows->where('type', TransactionType::Expense)->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero());

                return [
                    'month' => $month,
                    'label' => Carbon::create($year, $month, 1)->translatedFormat('M'),
                    'income' => $income,
                    'expense' => $expense,
                    'savings' => $income->subtract($expense),
                ];
            });

            $income = $months->reduce(fn (Money $c, $m) => $c->add($m['income']), Money::zero());
            $expense = $months->reduce(fn (Money $c, $m) => $c->add($m['expense']), Money::zero());
            $savings = $income->subtract($expense);

            $bestIncomeMonth = $months->sortByDesc(fn ($m) => $m['income']->toFloat())->first();
            $worstExpenseMonth = $months->sortByDesc(fn ($m) => $m['expense']->toFloat())->first();

            $topCategoryRow = $currencyRows->where('type', TransactionType::Expense)
                ->groupBy('category_id')
                ->map(fn ($group, $categoryId) => [
                    'category_id' => $categoryId,
                    'total' => $group->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero()),
                ])
                ->sortByDesc(fn ($row) => $row['total']->toFloat())
                ->first();

            $topCategory = null;
            if ($topCategoryRow) {
                $topCategory = [
                    'category' => Category::withoutGlobalScopes()->find($topCategoryRow['category_id']),
                    'total' => $topCategoryRow['total'],
                    'percentage' => $topCategoryRow['total']->percentageOf($expense),
                ];
            }

            $monthsWithData = $months->filter(fn ($m) => ! $m['income']->isZero() || ! $m['expense']->isZero())->count() ?: 1;

            return [$currency => [
                'income' => $income,
                'expense' => $expense,
                'savings' => $savings,
                'savingsRate' => $savings->percentageOf($income),
                'avgMonthlyIncome' => $income->divide($monthsWithData),
                'avgMonthlyExpense' => $expense->divide($monthsWithData),
                'bestIncomeMonth' => $bestIncomeMonth,
                'worstExpenseMonth' => $worstExpenseMonth,
                'topCategory' => $topCategory,
                'months' => $months,
            ]];
        });
    }

    /**
     * @return Collection<string, Collection<int, array{label: string, netWorth: Money}>>
     */
    public function netWorthEvolution(User $user, int $months = 12): Collection
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $accounts = $user->accounts()->get(['id', 'initial_balance', 'currency', 'created_at']);

        $flows = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.date', '>=', $start)
            ->selectRaw("DATE_FORMAT(transactions.date, '%Y-%m') as ym, transactions.type as type, accounts.currency as currency, SUM(transactions.amount) as total")
            ->groupBy('ym', 'type', 'currency')
            ->get()
            ->groupBy('currency');

        return $this->currenciesForUser($user)->mapWithKeys(function (string $currency) use ($start, $months, $accounts, $flows) {
            $currencyAccounts = $accounts->where('currency', $currency);
            $byMonth = ($flows->get($currency) ?? collect())->groupBy('ym');
            $cumulativeFlow = Money::zero();

            $series = collect(range(0, $months - 1))->map(function ($i) use ($start, $currencyAccounts, $byMonth, &$cumulativeFlow) {
                $monthEnd = $start->copy()->addMonths($i)->endOfMonth();
                $ym = $monthEnd->format('Y-m');
                $bucket = $byMonth->get($ym, collect());

                $income = Money::of($bucket->firstWhere('type', TransactionType::Income->value)->total ?? 0);
                $expense = Money::of($bucket->firstWhere('type', TransactionType::Expense->value)->total ?? 0);
                $cumulativeFlow = $cumulativeFlow->add($income)->subtract($expense);

                $baseline = $currencyAccounts
                    ->filter(fn ($account) => $account->created_at->lte($monthEnd))
                    ->reduce(fn (Money $carry, Account $account) => $carry->add($account->initial_balance), Money::zero());

                return [
                    'label' => $monthEnd->translatedFormat('M Y'),
                    'netWorth' => $baseline->add($cumulativeFlow),
                ];
            });

            return [$currency => $series];
        });
    }

    /**
     * @return Collection<string, array{
     *   income: Money, expense: Money, savings: Money, savingsRate: float,
     *   biggestExpense: ?Transaction, transactionCount: int, topCategory: ?array,
     * }>
     */
    private function periodTotals(User $user, int $year, int $month): Collection
    {
        $rows = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->whereIn('transactions.type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->select('transactions.*', 'accounts.currency as account_currency')
            ->get();

        return $this->currenciesForUser($user)->mapWithKeys(
            fn (string $currency) => [$currency => $this->summarizeRows($rows->where('account_currency', $currency))]
        );
    }

    /**
     * @param  Collection<int, Transaction>  $rows
     * @return array{income: Money, expense: Money, savings: Money, savingsRate: float, biggestExpense: ?Transaction, transactionCount: int, topCategory: ?array}
     */
    private function summarizeRows(Collection $rows): array
    {
        $income = $rows->where('type', TransactionType::Income)->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero());
        $expense = $rows->where('type', TransactionType::Expense)->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero());
        $savings = $income->subtract($expense);

        $biggestExpense = $rows->where('type', TransactionType::Expense)->sortByDesc(fn (Transaction $t) => $t->amount->toFloat())->first();

        $topCategoryRow = $rows->where('type', TransactionType::Expense)
            ->groupBy('category_id')
            ->map(fn ($group, $categoryId) => [
                'category_id' => $categoryId,
                'total' => $group->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero()),
            ])
            ->sortByDesc(fn ($row) => $row['total']->toFloat())
            ->first();

        $topCategory = null;
        if ($topCategoryRow) {
            $category = Category::withoutGlobalScopes()->find($topCategoryRow['category_id']);
            $topCategory = [
                'category' => $category,
                'total' => $topCategoryRow['total'],
                'percentage' => $topCategoryRow['total']->percentageOf($expense),
            ];
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'savings' => $savings,
            'savingsRate' => $savings->percentageOf($income),
            'biggestExpense' => $biggestExpense,
            'transactionCount' => $rows->count(),
            'topCategory' => $topCategory,
        ];
    }

    private function emptyPeriodTotals(): array
    {
        return [
            'income' => Money::zero(),
            'expense' => Money::zero(),
            'savings' => Money::zero(),
            'savingsRate' => 0.0,
            'biggestExpense' => null,
            'transactionCount' => 0,
            'topCategory' => null,
        ];
    }

    private function trailingDailyExpenseAverage(string $currency): Money
    {
        $total = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->where('accounts.currency', $currency)
            ->where('transactions.date', '>=', now()->subDays(30))
            ->sum('transactions.amount');

        return Money::of($total)->divide(30);
    }

    private function percentChange(Money $previous, Money $current): float
    {
        if ($previous->isZero()) {
            return $current->isZero() ? 0.0 : 100.0;
        }

        return $current->subtract($previous)->divide($previous->abs()->toDecimalString())->toFloat() * 100;
    }
}
