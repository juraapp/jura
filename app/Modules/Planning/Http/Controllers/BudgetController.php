<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Http\Requests\Budgets\StoreBudgetRequest;
use App\Modules\Planning\Http\Requests\Budgets\UpdateBudgetRequest;
use App\Modules\Planning\Models\Budget;
use App\Modules\Planning\Services\BudgetForecastService;
use App\Modules\Planning\Services\BudgetService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(BudgetService $service, BudgetForecastService $forecastService, LedgerQuery $ledger): Response
    {
        $now = Carbon::now();

        $budgets = Auth::user()->budgets()
            ->whereDate('period_start', '<=', $now)
            ->get()
            ->filter(fn (Budget $budget) => $budget->period_type->value === 'yearly'
                ? $budget->period_start->isSameYear($now)
                : $budget->period_start->isSameMonth($now))
            ->map(function (Budget $budget) use ($service, $forecastService) {
                $progress = $service->progress($budget);
                $forecast = $forecastService->summary($budget);

                return [
                    'id' => $budget->id,
                    'category' => [
                        'id' => $budget->category->id,
                        'name' => $budget->category->name,
                        'icon' => $budget->category->icon,
                        'color' => $budget->category->color,
                    ],
                    'currency' => $budget->currency,
                    'amount' => $budget->amount->toFloat(),
                    'allocatedAmount' => $budget->allocated_amount?->toFloat(),
                    'periodType' => $budget->period_type->value,
                    'periodTypeLabel' => $budget->period_type->label(),
                    'spent' => $progress['spent']->toFloat(),
                    'remaining' => $progress['remaining']->toFloat(),
                    'projected' => $forecast['projected']->toFloat(),
                    'effectiveRemaining' => $forecast['effectiveRemaining']->toFloat(),
                    'percentage' => $progress['percentage'],
                    'status' => $progress['status'],
                    'forecastCount' => $budget->forecasts()->where('is_anticipated', true)->count(),
                ];
            })
            ->values();

        $currencies = Auth::user()->accounts()->pluck('currency')->unique()->sort()->values();

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
            'expenseCategories' => $ledger->categoryOptions(CategoryType::Expense),
            'periodTypes' => collect(BudgetPeriodType::cases())->map(fn (BudgetPeriodType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'currencies' => $currencies->isEmpty() ? collect([Auth::user()->currency_default]) : $currencies,
        ]);
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $periodStart = $data['period_type'] === BudgetPeriodType::Yearly->value
            ? Carbon::now()->startOfYear()->toDateString()
            : Carbon::now()->startOfMonth()->toDateString();

        Auth::user()->budgets()->updateOrCreate(
            ['category_id' => $data['category_id'], 'currency' => $data['currency'], 'period_type' => $data['period_type'], 'period_start' => $periodStart],
            ['amount' => $data['amount']],
        );

        return back()->with('success', __('budgets.saved'));
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $budget->update($request->validated());

        return back()->with('success', __('budgets.saved'));
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return back()->with('success', __('budgets.deleted'));
    }
}
