<?php

namespace App\Notifications;

use App\Models\Budget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BudgetThresholdReached extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Budget $budget,
        public readonly float $percentage,
        public readonly bool $exceeded,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $category = $this->budget->category->name;

        return [
            'type' => 'budget_alert',
            'severity' => $this->exceeded ? 'critical' : 'warning',
            'message_key' => $this->exceeded
                ? 'You exceeded your :category budget (:percentage% used).'
                : "You've spent :percentage% of your :category budget.",
            'message_params' => [
                'category' => $category,
                'percentage' => $this->percentageLabel(),
            ],
            'budget_id' => $this->budget->id,
            'url' => route('budgets.index'),
        ];
    }

    private function percentageLabel(): string
    {
        return number_format($this->percentage, 0);
    }
}
