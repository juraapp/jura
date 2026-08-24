<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\RecurringFrequency;
use App\Http\Requests\RecurringTransactions\StoreRecurringTransactionRequest;
use App\Http\Requests\RecurringTransactions\UpdateRecurringTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    public function index(): Response
    {
        $recurrences = Auth::user()->recurringTransactions()
            ->with(['account' => fn ($query) => $query->withTrashed(), 'category'])
            ->orderBy('next_due_date')
            ->get()
            ->map(fn (RecurringTransaction $recurring) => [
                'id' => $recurring->id,
                'type' => $recurring->type->value,
                'description' => $recurring->description,
                'amount' => $recurring->amount->toFloat(),
                'account' => [
                    'id' => $recurring->account->id,
                    'name' => $recurring->account->name,
                    'currency' => $recurring->account->currency,
                ],
                'category' => [
                    'id' => $recurring->category->id,
                    'name' => $recurring->category->name,
                    'icon' => $recurring->category->icon,
                    'color' => $recurring->category->color,
                ],
                'frequency' => $recurring->frequency->value,
                'frequencyLabel' => $recurring->frequency->label(),
                'nextDueDate' => $recurring->next_due_date->toDateString(),
                'endDate' => $recurring->end_date?->toDateString(),
                'isActive' => $recurring->is_active,
            ]);

        return Inertia::render('RecurringTransactions/Index', [
            'recurrences' => $recurrences,
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'expenseCategories' => Category::where('type', CategoryType::Expense)->orderBy('name')->get(['id', 'name']),
            'incomeCategories' => Category::where('type', CategoryType::Income)->orderBy('name')->get(['id', 'name']),
            'frequencies' => collect(RecurringFrequency::cases())->map(fn (RecurringFrequency $frequency) => [
                'value' => $frequency->value,
                'label' => $frequency->label(),
            ]),
        ]);
    }

    public function store(StoreRecurringTransactionRequest $request): RedirectResponse
    {
        Auth::user()->recurringTransactions()->create([...$request->validated(), 'is_active' => true]);

        return back()->with('success', __('recurring-transactions.saved'));
    }

    public function update(UpdateRecurringTransactionRequest $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $recurringTransaction->update($request->validated());

        return back()->with('success', __('recurring-transactions.saved'));
    }

    public function toggleActive(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorize('update', $recurringTransaction);

        $recurringTransaction->update(['is_active' => ! $recurringTransaction->is_active]);

        return back();
    }

    public function destroy(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorize('delete', $recurringTransaction);

        $recurringTransaction->delete();

        return back()->with('success', __('recurring-transactions.deleted'));
    }
}
