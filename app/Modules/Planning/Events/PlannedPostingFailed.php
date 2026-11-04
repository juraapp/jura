<?php

namespace App\Modules\Planning\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An auto-posting plan could not record its transaction (fired once per new reason, not on every retry).
 * $reason is account_inactive, category_missing or failed.
 */
class PlannedPostingFailed
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly int $plannedEventId,
        public readonly string $reason,
    ) {}
}
