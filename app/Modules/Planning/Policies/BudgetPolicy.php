<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Planning\Models\Budget;
use App\Modules\Identity\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }
}
