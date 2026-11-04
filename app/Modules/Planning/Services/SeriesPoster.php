<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Events\PlannedPostingFailed;
use App\Modules\Planning\Models\PlannedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records auto-posting plans as real transactions once their date is the owner's today or earlier (every
 * missed date in one run). A plan that cannot be recorded keeps a post_error and is retried on the next run;
 * one bad plan never stops the batch for everyone.
 */
class SeriesPoster
{
    public const ACCOUNT_INACTIVE = 'account_inactive';

    public const CATEGORY_MISSING = 'category_missing';

    public const FAILED = 'failed';

    public function __construct(private readonly PlannedEventService $planned) {}

    /**
     * Every user's due auto-posting plans (hourly job). Returns how many were recorded.
     */
    public function postDue(): int
    {
        $posted = 0;
        /** @var array<int, CarbonImmutable> $todays */
        $todays = [];

        $this->dueQuery(now()->addDay()->toDateString())
            ->chunkById(100, function ($events) use (&$posted, &$todays) {
                foreach ($events as $event) {
                    if ($event->user === null) {
                        continue;
                    }

                    $today = $todays[$event->user_id] ??= $event->user->today();
                    if ($event->date->gt($today) || ! $this->tryPost($event)) {
                        continue;
                    }

                    $posted++;
                }
            });

        return $posted;
    }

    /**
     * One user's due auto-posting plans, right after they create or edit one dated today or earlier.
     */
    public function postDueForUser(User $user): int
    {
        $posted = 0;

        $this->dueQuery($user->today()->toDateString())
            ->where('planned_events.user_id', $user->id)
            ->orderBy('date')
            ->get()
            ->each(function (PlannedEvent $event) use (&$posted) {
                if ($this->tryPost($event)) {
                    $posted++;
                }
            });

        return $posted;
    }

    /**
     * @return Builder<PlannedEvent>
     */
    private function dueQuery(string $until)
    {
        return PlannedEvent::withoutGlobalScope('user')
            ->open()
            ->where('auto_post', true)
            ->where('date', '<=', $until)
            ->with([
                'user',
                'account' => fn ($query) => $query->withoutGlobalScope('user')->withTrashed(),
                'counterAccount' => fn ($query) => $query->withoutGlobalScope('user')->withTrashed(),
                'category' => fn ($query) => $query->withoutGlobalScope('visibleToUser')->withTrashed(),
            ]);
    }

    private function tryPost(PlannedEvent $event): bool
    {
        $problem = match (true) {
            $event->account === null || $event->account->trashed() || ! $event->account->is_active => self::ACCOUNT_INACTIVE,
            $event->isTransfer() && ($event->counterAccount === null || $event->counterAccount->trashed() || ! $event->counterAccount->is_active) => self::ACCOUNT_INACTIVE,
            ! $event->isTransfer() && ($event->category === null || $event->category->trashed()) => self::CATEGORY_MISSING,
            default => null,
        };

        if ($problem !== null) {
            Log::warning('Skipped an auto-posting plan.', [
                'planned_event_id' => $event->id,
                'reason' => $problem,
                'account_id' => $event->account_id,
                'category_id' => $event->category_id,
            ]);
            $this->blocked($event, $problem);

            return false;
        }

        try {
            return $this->planned->realize($event, [], autoPosted: true) !== null;
        } catch (Throwable $exception) {
            report($exception);
            $this->blocked($event, self::FAILED);

            return false;
        }
    }

    /**
     * Remembers why a plan could not be recorded, and tells its owner once per new reason.
     */
    private function blocked(PlannedEvent $event, string $reason): void
    {
        if ($event->post_error === $reason) {
            return;
        }

        $event->forceFill(['post_error' => $reason])->save();
        PlannedPostingFailed::dispatch($event->user, $event->id, $reason);
    }
}
