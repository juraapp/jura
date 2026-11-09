<?php

namespace App\Modules\Notifications\Console\Commands;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\BucketBehindSchedule;
use App\Modules\Planning\Contracts\SavingsTargets;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-behind-savings-buckets')]
#[Description('Notify users whose savings buckets are falling behind their target date')]
class NotifyBehindSavingsBuckets extends Command
{
    public function handle(SavingsTargets $targets): void
    {
        // There is no logged-in user here, so the buckets are read unscoped and each user's targets are asked for by user.
        $userIds = Bucket::query()
            ->where('kind', BucketKind::Savings->value)
            ->whereNotNull('target_date')
            ->whereNotNull('cap_amount')
            ->whereNull('completed_on')
            ->distinct()
            ->pluck('user_id');

        $flagged = 0;
        $reviewed = 0;

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            foreach ($targets->targets($user) as $target) {
                $reviewed++;

                if (! $target['behind'] || $target['overdue']) {
                    continue;
                }

                $alreadyNotified = $user->unreadNotifications()
                    ->where('type', BucketBehindSchedule::class)
                    ->where('data->bucket_id', $target['bucketId'])
                    ->exists();

                if (! $alreadyNotified) {
                    $user->notify(new BucketBehindSchedule($target['bucketId'], $target['name']));
                    $flagged++;
                }
            }
        }

        $this->info("Buckets flagged as behind schedule: {$flagged} of {$reviewed} reviewed.");
    }
}
