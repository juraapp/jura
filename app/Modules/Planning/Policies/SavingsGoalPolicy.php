<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Planning\Models\SavingsGoal;
use App\Modules\Identity\Models\User;

class SavingsGoalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavingsGoal $savingsGoal): bool
    {
        return $user->id === $savingsGoal->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SavingsGoal $savingsGoal): bool
    {
        return $user->id === $savingsGoal->user_id;
    }

    public function delete(User $user, SavingsGoal $savingsGoal): bool
    {
        return $user->id === $savingsGoal->user_id;
    }
}
