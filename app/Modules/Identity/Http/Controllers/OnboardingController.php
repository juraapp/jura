<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The setup wizard a new user sees the first time they enter Jura. Each step saves through
 * the regular endpoints (preferences, accounts, planned events, buckets, goals); this
 * controller only renders the wizard and records that it was finished or skipped.
 */
class OnboardingController extends Controller
{
    public function show(Request $request, LedgerQuery $ledger): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/Index', [
            'currencies' => Currencies::options(),
            'timezones' => PreferenceController::TIMEZONES,
            'preferences' => $user->only([
                'currency_default', 'locale', 'timezone', 'date_format', 'time_format', 'decimal_separator',
                'thousands_separator', 'fraction_digits', 'currency_display', 'currency_position',
            ]),
            'accountTypes' => collect(AccountType::cases())->map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
            ]),
            'accounts' => $ledger->activeAccountOptions(),
            'expenseCategories' => $ledger->categoryOptions(CategoryType::Expense, ['id', 'name', 'icon', 'color']),
            'salaryCategoryId' => $ledger->categoryOptions(CategoryType::Income)->firstWhere('name', 'Salary')?->id,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed_at === null) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return redirect()->route('dashboard');
    }
}
