<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\InsightService;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function monthly(Request $request, ReportService $reports, InsightService $insights): Response
    {
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);
        $user = Auth::user();

        $summary = $reports->monthlySummary($user, $year, $month);
        $expenseByCategory = $reports->expenseByCategory($user, $year, $month);

        $fixedExpenseByCurrency = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->where('transactions.is_recurring_generated', true)
            ->selectRaw('accounts.currency as currency, SUM(transactions.amount) as total')
            ->groupBy('accounts.currency')
            ->get()
            ->keyBy('currency');

        $currencies = $summary->map(function (array $currencySummary, string $currency) use ($insights, $expenseByCategory, $fixedExpenseByCurrency, $user, $year, $month) {
            $fixedExpense = Money::of($fixedExpenseByCurrency->get($currency)->total ?? 0);
            $variableExpense = $currencySummary['expense']->subtract($fixedExpense);

            return [
                'currency' => $currency,
                'income' => $currencySummary['income']->toFloat(),
                'expense' => $currencySummary['expense']->toFloat(),
                'savings' => $currencySummary['savings']->toFloat(),
                'savingsRate' => $currencySummary['savingsRate'],
                'netWorth' => $currencySummary['netWorth']->toFloat(),
                'fixedExpense' => $fixedExpense->toFloat(),
                'variableExpense' => $variableExpense->toFloat(),
                'highlights' => $insights->monthlyHighlights($currencySummary, $user, $year, $month),
                'expenseByCategory' => $expenseByCategory->get($currency, collect())->map(fn (array $row) => [
                    'category' => $row['category'] ? [
                        'id' => $row['category']->id,
                        'name' => $row['category']->name,
                        'icon' => $row['category']->icon,
                        'color' => $row['category']->color,
                    ] : null,
                    'total' => $row['total']->toFloat(),
                    'percentage' => $row['percentage'],
                ])->values(),
            ];
        })->values();

        return Inertia::render('Reports/Monthly', [
            'year' => $year,
            'month' => $month,
            'periodLabel' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'currencies' => $currencies,
        ]);
    }

    public function annual(Request $request, ReportService $reports): Response
    {
        $year = (int) $request->integer('year', now()->year);
        $user = Auth::user();

        $summary = $reports->annualSummary($user, $year);

        $currencies = $summary->map(fn (array $currencySummary, string $currency) => [
            'currency' => $currency,
            'income' => $currencySummary['income']->toFloat(),
            'expense' => $currencySummary['expense']->toFloat(),
            'savings' => $currencySummary['savings']->toFloat(),
            'savingsRate' => $currencySummary['savingsRate'],
            'avgMonthlyIncome' => $currencySummary['avgMonthlyIncome']->toFloat(),
            'avgMonthlyExpense' => $currencySummary['avgMonthlyExpense']->toFloat(),
            'bestIncomeMonth' => $currencySummary['bestIncomeMonth'] ? [
                'label' => $currencySummary['bestIncomeMonth']['label'],
                'income' => $currencySummary['bestIncomeMonth']['income']->toFloat(),
            ] : null,
            'worstExpenseMonth' => $currencySummary['worstExpenseMonth'] ? [
                'label' => $currencySummary['worstExpenseMonth']['label'],
                'expense' => $currencySummary['worstExpenseMonth']['expense']->toFloat(),
            ] : null,
            'topCategory' => $currencySummary['topCategory'] ? [
                'name' => $currencySummary['topCategory']['category']?->name,
                'percentage' => $currencySummary['topCategory']['percentage'],
            ] : null,
            'months' => collect($currencySummary['months'])->map(fn (array $month) => [
                'label' => $month['label'],
                'income' => $month['income']->toFloat(),
                'expense' => $month['expense']->toFloat(),
                'savings' => $month['savings']->toFloat(),
            ])->values(),
        ])->values();

        return Inertia::render('Reports/Annual', [
            'year' => $year,
            'currencies' => $currencies,
        ]);
    }

    public function custom(Request $request): Response
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();

        $transactions = Transaction::query()
            ->with(['account', 'category'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $totals = $transactions->groupBy(fn (Transaction $transaction) => $transaction->account->currency)
            ->map(function (Collection $rows, string $currency) {
                $income = $rows->where('type', TransactionType::Income)->reduce(fn (Money $carry, Transaction $t) => $carry->add($t->amount), Money::zero());
                $expense = $rows->where('type', TransactionType::Expense)->reduce(fn (Money $carry, Transaction $t) => $carry->add($t->amount), Money::zero());

                return [
                    'currency' => $currency,
                    'income' => $income->toFloat(),
                    'expense' => $expense->toFloat(),
                    'savings' => $income->subtract($expense)->toFloat(),
                ];
            })
            ->values();

        return Inertia::render('Reports/Custom', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => $totals,
            'transactions' => $transactions->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'type' => $transaction->type->value,
                'description' => $transaction->description,
                'category' => $transaction->category?->name,
                'account' => $transaction->account->name,
                'amount' => $transaction->amount->toFloat(),
                'currency' => $transaction->account->currency,
            ])->values(),
        ]);
    }
}
