<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Notifications\PlannedPostingBlocked;
use App\Modules\Planning\Events\PlannedPostingFailed;
use App\Modules\Planning\Models\PlannedEvent;

/**
 * Tells the owner an auto-posting plan could not be recorded. The poster fires the event once per new reason.
 */
class NotifyPlannedPostingFailed
{
    public function handle(PlannedPostingFailed $event): void
    {
        $plan = PlannedEvent::forUser($event->user)->find($event->plannedEventId);

        if ($plan !== null) {
            $event->user->notify(new PlannedPostingBlocked($plan, $event->reason));
        }
    }
}
