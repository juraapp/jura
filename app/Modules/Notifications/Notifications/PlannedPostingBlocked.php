<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An auto-posting plan could not be recorded (its account is archived or deleted, its category deleted, or it failed).
 */
class PlannedPostingBlocked extends Notification
{
    use Queueable;

    public function __construct(public readonly PlannedEvent $event, public readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'planned_post_blocked',
            'severity' => 'critical',
            'message' => __('notifications.planned_post_blocked', [
                'label' => $this->event->description,
                'reason' => __('notifications.post_blocked_reason.'.$this->reason),
            ]),
            'planned_event_id' => $this->event->id,
            'url' => route('planned-events.index', ['section' => 'review']),
        ];
    }
}
