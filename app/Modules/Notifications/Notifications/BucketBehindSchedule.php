<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A savings bucket with a target date is falling behind it (see `Planning\Contracts\SavingsTargets`).
 */
class BucketBehindSchedule extends Notification
{
    use Queueable;

    public function __construct(public readonly int $bucketId, public readonly string $name) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bucket_behind',
            'severity' => 'warning',
            'message' => __('notifications.bucket_behind_schedule', ['bucket' => $this->name]),
            'bucket_id' => $this->bucketId,
            'url' => route('buckets.index'),
        ];
    }
}
