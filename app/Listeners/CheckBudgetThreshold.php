<?php

namespace App\Listeners;

use App\Events\TransactionsMutated;
use App\Notifications\BudgetThresholdReached;
use App\Services\BudgetService;
use Illuminate\Support\Carbon;

/**
 * Re-evaluates the user's active budgets after any transaction change and
 * notifies once per threshold crossing (never re-notifies while an unread
 * alert for that same budget is already sitting in their bell).
 */
class CheckBudgetThreshold
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function handle(TransactionsMutated $event): void
    {
        $now = Carbon::now();

        $activeBudgets = $event->user->budgets()->with('category')->get()->filter(function ($budget) use ($now) {
            return $budget->period_type->value === 'yearly'
                ? $budget->period_start->isSameYear($now)
                : $budget->period_start->isSameMonth($now);
        });

        foreach ($activeBudgets as $budget) {
            $progress = $this->budgets->progress($budget);

            if ($progress['status'] === 'on_track') {
                continue;
            }

            $alreadyNotified = $event->user->unreadNotifications()
                ->where('type', BudgetThresholdReached::class)
                ->where('data->budget_id', $budget->id)
                ->exists();

            if (! $alreadyNotified) {
                $event->user->notify(new BudgetThresholdReached($budget, $progress['percentage'], $progress['status'] === 'exceeded'));
            }
        }
    }
}
