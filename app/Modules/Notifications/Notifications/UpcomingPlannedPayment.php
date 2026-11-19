<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A planned expense is due within three days: it will be recorded automatically, or it needs confirming.
 */
class UpcomingPlannedPayment extends Notification
{
    use Queueable;

    public function __construct(public readonly PlannedEvent $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'upcoming_payment',
            'severity' => 'warning',
            'message' => __($this->event->auto_post ? 'notifications.upcoming_planned_auto' : 'notifications.upcoming_planned_manual', [
                'label' => $this->event->description,
                'amount' => $notifiable->formatter()->money($this->event->amount, $this->event->currency),
                'date' => $notifiable->formatter()->date($this->event->date, false),
            ]),
            'planned_event_id' => $this->event->id,
            'url' => route('planned-events.index', ['section' => 'review']),
        ];
    }
}
