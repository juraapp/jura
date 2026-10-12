<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Notifications\Notifications\BudgetThresholdReached;
use App\Modules\Planning\Contracts\BucketProgress;

/**
 * Re-evaluates the user's active budgets after any transaction change and
 * notifies once per threshold crossing (never re-notifies while an unread
 * alert for that same budget is already sitting in their bell).
 */
class CheckBudgetThreshold
{
    public function __construct(private readonly BucketProgress $limits) {}

    public function handle(TransactionsMutated $event): void
    {
        $limitBuckets = $event->user->buckets()->with('category')->get()
            ->filter(fn ($bucket) => $bucket->tracksLimit());

        foreach ($limitBuckets as $bucket) {
            $progress = $this->limits->progress($bucket);

            if ($progress['status'] === 'on_track') {
                continue;
            }

            $alreadyNotified = $event->user->unreadNotifications()
                ->where('type', BudgetThresholdReached::class)
                ->where('data->budget_id', $bucket->id)
                ->exists();

            if (! $alreadyNotified) {
                $event->user->notify(new BudgetThresholdReached($bucket, $progress['percentage'], $progress['status'] === 'exceeded'));
            }
        }
    }
}
