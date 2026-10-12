<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Models\PlannedEvent;

class PlannedEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PlannedEvent $plannedEvent): bool
    {
        return $user->id === $plannedEvent->user_id;
    }

    public function delete(User $user, PlannedEvent $plannedEvent): bool
    {
        return $user->id === $plannedEvent->user_id;
    }
}
