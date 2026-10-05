<?php

namespace App\Console\Commands;

use App\Enums\SavingsGoalStatus;
use App\Models\SavingsGoal;
use App\Notifications\GoalBehindSchedule;
use App\Support\Money;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('app:notify-behind-savings-goals')]
#[Description('Notify active savings goals that are falling behind their target date')]
class NotifyBehindSavingsGoals extends Command
{
    public function handle(): void
    {
        $goals = SavingsGoal::with('user')
            ->where('status', SavingsGoalStatus::Active)
            ->whereNotNull('target_date')
            ->where('target_date', '>', now())
            ->get();

        $flagged = 0;

        foreach ($goals as $goal) {
            $saved = Money::of($goal->contributions()->sum('amount'));
            $percentage = $saved->percentageOf($goal->target_amount);

            $totalMonths = max(1, $goal->created_at->floatDiffInMonths($goal->target_date));
            $elapsedMonths = min($totalMonths, max(0, $goal->created_at->floatDiffInMonths(Carbon::now())));
            $expectedPercentage = ($elapsedMonths / $totalMonths) * 100;

            if ($percentage + 2 >= $expectedPercentage) {
                continue;
            }

            $alreadyNotified = $goal->user->unreadNotifications()
                ->where('type', GoalBehindSchedule::class)
                ->where('data->savings_goal_id', $goal->id)
                ->exists();

            if (! $alreadyNotified) {
                $goal->user->notify(new GoalBehindSchedule($goal));
                $flagged++;
            }
        }

        $this->info("Goals flagged as behind schedule: {$flagged} of {$goals->count()} reviewed.");
    }
}
