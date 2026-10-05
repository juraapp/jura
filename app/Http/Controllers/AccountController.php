<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Models\Account;
use App\Services\BalanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(BalanceCalculator $calculator): Response
    {
        $accounts = Account::orderBy('is_active', 'desc')->orderBy('name')->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'typeLabel' => $account->type->label(),
                'icon' => $account->type->icon(),
                'institution' => $account->institution,
                'initial_balance' => $account->initial_balance->toFloat(),
                'currency' => $account->currency,
                'color' => $account->color,
                'masked_number' => $account->masked_number,
                'is_active' => $account->is_active,
                'notes' => $account->notes,
                'balance' => $calculator->accountBalance($account)->toFloat(),
            ]);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'accountTypes' => collect(AccountType::cases())->map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        Auth::user()->accounts()->create($request->validated());

        return back()->with('success', __('accounts.saved'));
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->validated());

        return back()->with('success', __('accounts.saved'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        if ($account->transactions()->exists() || $account->recurringTransactions()->exists()) {
            $account->update(['is_active' => false]);

            return back()->with('warning', __('accounts.archived'));
        }

        $account->delete();

        return back()->with('success', __('accounts.deleted'));
    }
}
