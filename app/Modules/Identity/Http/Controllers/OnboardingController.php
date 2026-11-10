<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The setup wizard a new user sees the first time they enter Jura. Each step saves through
 * the regular endpoints (preferences, accounts, planned events, buckets); this
 * controller only renders the wizard and records that it was finished or skipped.
 */
class OnboardingController extends Controller
{
    /**
     * System expense categories (seeded by name in CategorySeeder) the wizard offers first for spending buckets.
     *
     * @var list<string>
     */
    private const SUGGESTED_CATEGORIES = ['Food', 'Transportation', 'Housing', 'Utilities', 'Entertainment', 'Shopping'];

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
            'accounts' => $ledger->activeAccountOptions($user->id),
            'suggestedCategories' => $this->suggestedCategories($ledger, $user->id),
            'salaryCategoryId' => $ledger->categoryOptions($user->id, CategoryType::Income)->firstWhere('name', 'Salary')?->id,
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

    /**
     * The suggested system categories in the order above, or the first six expense categories when none of them exist.
     *
     * @return Collection<int, mixed>
     */
    private function suggestedCategories(LedgerQuery $ledger, int $userId): Collection
    {
        $categories = $ledger->categoryOptions($userId, CategoryType::Expense, ['id', 'name', 'icon', 'color']);
        $suggested = collect(self::SUGGESTED_CATEGORIES)
            ->map(fn (string $name) => $categories->firstWhere('name', $name))
            ->filter()
            ->values();

        return $suggested->isNotEmpty() ? $suggested : $categories->take(6)->values();
    }
}
