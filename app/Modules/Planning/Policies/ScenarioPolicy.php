<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Models\Scenario;

class ScenarioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Scenario $scenario): bool
    {
        return $user->id === $scenario->user_id;
    }
}
