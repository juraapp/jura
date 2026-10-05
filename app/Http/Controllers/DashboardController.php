<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\BalanceCalculator;
use App\Services\InsightService;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reports, BalanceCalculator $balances, InsightService $insights): Response
    {
        $user = $request->user();
        $evolutionMonths = $request->integer('evolutionMonths', 6) === 12 ? 12 : 6;

        $hasAnyAccount = $user->accounts()->exists();
        $hasAnyTransaction = $user->transactions()->exists();

        if (! $hasAnyAccount || ! $hasAnyTransaction) {
            return Inertia::render('Dashboard', [
                'evolutionMonths' => $evolutionMonths,
                'hasAnyAccount' => $hasAnyAccount,
                'hasAnyTransaction' => $hasAnyTransaction,
                'currencies' => [],
            ]);
        }

        $now = Carbon::now();
        $summary = $reports->monthlySummary($user, $now->year, $now->month);
        $expenseByCategory = $reports->expenseByCategory($user, $now->year, $now->month);
        $expenseByAccount = $reports->expenseByAccount($user, $now->year, $now->month)
            ->groupBy(fn (array $row) => $row['account']->currency);
        $moneyDistribution = $reports->moneyDistribution($user)
            ->groupBy(fn (array $row) => $row['account']->currency);
        $monthlyEvolution = $reports->monthlyEvolution($user, $evolutionMonths);
        $netWorthEvolution = $reports->netWorthEvolution($user, $evolutionMonths);
        $netWorthByCurrency = $balances->netWorthByCurrency($user);

        $currencies = $summary->keys()->map(fn (string $currency) => [
            'currency' => $currency,
            'netWorth' => $netWorthByCurrency->get($currency, Money::zero())->toFloat(),
            'summary' => $this->serializeSummary($summary->get($currency)),
            'highlights' => $insights->monthlyHighlights($summary->get($currency), $user, $now->year, $now->month),
            'expenseByCategory' => $expenseByCategory->get($currency, collect())->map(fn (array $row) => [
                'name' => $row['category']?->name,
                'color' => $row['category']?->color ?? '#6b7280',
                'total' => $row['total']->toFloat(),
                'percentage' => $row['percentage'],
            ])->values(),
            'expenseByAccount' => $expenseByAccount->get($currency, collect())->map(fn (array $row) => [
                'name' => $row['account']->name,
                'color' => $row['account']->color,
                'total' => $row['total']->toFloat(),
            ])->values(),
            'moneyDistribution' => $moneyDistribution->get($currency, collect())->map(fn (array $row) => [
                'name' => $row['account']->name,
                'color' => $row['account']->color,
                'balance' => $row['balance']->toFloat(),
            ])->values(),
            'monthlyEvolution' => $this->serializeSeries($monthlyEvolution->get($currency, collect())),
            'netWorthEvolution' => $netWorthEvolution->get($currency, collect())->map(fn (array $row) => [
                'label' => $row['label'],
                'netWorth' => $row['netWorth']->toFloat(),
            ])->values(),
        ])->values();

        return Inertia::render('Dashboard', [
            'evolutionMonths' => $evolutionMonths,
            'hasAnyAccount' => true,
            'hasAnyTransaction' => true,
            'currencies' => $currencies,
        ]);
    }

    /**
     * @param  array{income: Money, expense: Money, savings: Money, savingsRate: float, avgDailyExpense: Money, biggestExpense: ?Transaction, daysOfAutonomy: ?float, topCategory: ?array, changeIncome: float, changeExpense: float}  $summary
     * @return array<string, mixed>
     */
    private function serializeSummary(array $summary): array
    {
        return [
            'income' => $summary['income']->toFloat(),
            'expense' => $summary['expense']->toFloat(),
            'savings' => $summary['savings']->toFloat(),
            'savingsRate' => $summary['savingsRate'],
            'avgDailyExpense' => $summary['avgDailyExpense']->toFloat(),
            'biggestExpense' => $summary['biggestExpense'] ? [
                'amount' => $summary['biggestExpense']->amount->toFloat(),
                'description' => $summary['biggestExpense']->description ?: $summary['biggestExpense']->category?->name,
            ] : null,
            'daysOfAutonomy' => $summary['daysOfAutonomy'],
            'topCategory' => $summary['topCategory'] ? [
                'name' => $summary['topCategory']['category']?->name,
                'percentage' => $summary['topCategory']['percentage'],
            ] : null,
            'changeIncome' => $summary['changeIncome'],
            'changeExpense' => $summary['changeExpense'],
        ];
    }

    /**
     * @param  Collection<int, array{label: string, income: Money, expense: Money, savings: Money}>  $rows
     * @return Collection<int, array{label: string, income: float, expense: float, savings: float}>
     */
    private function serializeSeries(Collection $rows): Collection
    {
        return $rows->map(fn (array $row) => [
            'label' => $row['label'],
            'income' => $row['income']->toFloat(),
            'expense' => $row['expense']->toFloat(),
            'savings' => $row['savings']->toFloat(),
        ])->values();
    }
}
