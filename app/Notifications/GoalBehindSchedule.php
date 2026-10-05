<?php

namespace App\Notifications;

use App\Models\SavingsGoal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GoalBehindSchedule extends Notification
{
    use Queueable;

    public function __construct(public readonly SavingsGoal $goal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'goal_behind',
            'severity' => 'warning',
            'message_key' => 'Your goal ":name" is behind its target date.',
            'message_params' => ['name' => $this->goal->name],
            'savings_goal_id' => $this->goal->id,
            'url' => route('savings-goals.index'),
        ];
    }
}
