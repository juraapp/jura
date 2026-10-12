<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Planning\Http\Requests\BudgetForecasts\StoreBudgetForecastRequest;
use App\Modules\Planning\Http\Requests\BudgetForecasts\UpdateBudgetForecastRequest;
use App\Modules\Planning\Models\Budget;
use App\Modules\Planning\Models\BudgetForecast;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BudgetForecastController extends Controller
{
    public function index(Budget $budget, LedgerQuery $ledger): Response
    {
        $forecasts = $budget->forecasts()
            ->orderBy('date')
            ->get()
            ->map(fn (BudgetForecast $forecast) => [
                'id' => $forecast->id,
                'description' => $forecast->description,
                'amount' => $forecast->amount->toFloat(),
                'date' => $forecast->date->toDateString(),
                'isAnticipated' => $forecast->is_anticipated,
                'currency' => $forecast->currency,
                'notes' => $forecast->notes,
                'category' => $forecast->category ? [
                    'id' => $forecast->category->id,
                    'name' => $forecast->category->name,
                    'icon' => $forecast->category->icon,
                    'color' => $forecast->category->color,
                ] : null,
            ])
            ->values();

        $categories = $ledger->categoryOptions(CategoryType::Expense, ['id', 'name', 'icon', 'color']);

        return Inertia::render('BudgetForecasts/Index', [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->category->name,
                'amount' => $budget->amount->toFloat(),
                'currency' => $budget->currency,
                'periodType' => $budget->period_type->value,
            ],
            'forecasts' => $forecasts,
            'categories' => $categories,
        ]);
    }

    public function store(StoreBudgetForecastRequest $request, Budget $budget): RedirectResponse
    {
        $data = $request->validated();
        $data['budget_id'] = $budget->id;

        $budget->forecasts()->create($data);

        return back()->with('success', __('budget-forecasts.saved'));
    }

    public function update(UpdateBudgetForecastRequest $request, Budget $budget, BudgetForecast $budgetForecast): RedirectResponse
    {
        $this->authorize('update', $budgetForecast);

        $budgetForecast->update($request->validated());

        return back()->with('success', __('budget-forecasts.updated'));
    }

    public function destroy(Budget $budget, BudgetForecast $budgetForecast): RedirectResponse
    {
        $this->authorize('delete', $budgetForecast);

        $budgetForecast->delete();

        return back()->with('success', __('budget-forecasts.deleted'));
    }
}
