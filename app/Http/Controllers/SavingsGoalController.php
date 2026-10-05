<?php

namespace App\Http\Controllers;

use App\Enums\SavingsGoalStatus;
use App\Http\Requests\SavingsGoals\StoreSavingsGoalContributionRequest;
use App\Http\Requests\SavingsGoals\StoreSavingsGoalRequest;
use App\Http\Requests\SavingsGoals\UpdateSavingsGoalRequest;
use App\Models\SavingsGoal;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SavingsGoalController extends Controller
{
    private const ICON_CHOICES = [
        'flag', 'banknotes', 'wallet', 'home', 'calendar', 'chart-pie',
        'arrow-trending-up', 'tag', 'sun', 'check-circle', 'cog', 'document-chart-bar',
    ];

    public function index(): Response
    {
        $currencies = Auth::user()->accounts()->pluck('currency')->unique()->sort()->values();
        $currencies = $currencies->isEmpty() ? collect([Auth::user()->currency_default]) : $currencies;

        $now = Carbon::now();

        $goals = Auth::user()->savingsGoals()
            ->whereIn('status', [SavingsGoalStatus::Active, SavingsGoalStatus::Completed])
            ->orderBy('status')
            ->orderBy('target_date')
            ->get()
            ->map(function (SavingsGoal $goal) use ($now) {
                $saved = Money::of($goal->contributions()->sum('amount'));
                $percentage = $saved->percentageOf($goal->target_amount);

                $suggestedMonthly = null;
                $schedule = null;

                if ($goal->target_date && $goal->status === SavingsGoalStatus::Active) {
                    $monthsRemaining = max(1, (int) ceil($now->floatDiffInMonths($goal->target_date, false)));
                    $remaining = $goal->target_amount->subtract($saved);
                    $suggestedMonthly = $remaining->isPositive() ? $remaining->divide($monthsRemaining) : Money::zero();

                    $totalMonths = max(1, $goal->created_at->floatDiffInMonths($goal->target_date));
                    $elapsedMonths = min($totalMonths, max(0, $goal->created_at->floatDiffInMonths($now)));
                    $expectedPercentage = ($elapsedMonths / $totalMonths) * 100;
                    $schedule = $percentage + 2 >= $expectedPercentage ? 'ahead' : 'behind';
                }

                return [
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'targetAmount' => $goal->target_amount->toFloat(),
                    'currency' => $goal->currency,
                    'targetDate' => $goal->target_date?->toDateString(),
                    'icon' => $goal->icon,
                    'color' => $goal->color,
                    'status' => $goal->status->value,
                    'saved' => $saved->toFloat(),
                    'percentage' => min($percentage, 100),
                    'suggestedMonthly' => $suggestedMonthly?->toFloat(),
                    'schedule' => $schedule,
                ];
            })
            ->values();

        return Inertia::render('SavingsGoals/Index', [
            'goals' => $goals,
            'currencies' => $currencies,
            'iconChoices' => self::ICON_CHOICES,
        ]);
    }

    public function store(StoreSavingsGoalRequest $request): RedirectResponse
    {
        Auth::user()->savingsGoals()->create($request->validated());

        return back()->with('success', __('savings-goals.saved'));
    }

    public function update(UpdateSavingsGoalRequest $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $savingsGoal->update($request->validated());

        return back()->with('success', __('savings-goals.saved'));
    }

    public function destroy(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('delete', $savingsGoal);

        $savingsGoal->delete();

        return back()->with('success', __('savings-goals.deleted'));
    }

    public function contribute(StoreSavingsGoalContributionRequest $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $data = $request->validated();

        $savingsGoal->contributions()->create($data);

        $saved = $savingsGoal->contributions()->sum('amount');

        if ($saved >= $savingsGoal->target_amount->toFloat() && $savingsGoal->status === SavingsGoalStatus::Active) {
            $savingsGoal->update(['status' => SavingsGoalStatus::Completed]);
        }

        return back()->with('success', __('savings-goals.contribution_recorded'));
    }
}
