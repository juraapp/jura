<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Models\PlannedEventSeries;

class PlannedEventSeriesPolicy
{
    public function update(User $user, PlannedEventSeries $series): bool
    {
        return $user->id === $series->user_id;
    }

    public function delete(User $user, PlannedEventSeries $series): bool
    {
        return $user->id === $series->user_id;
    }
}
