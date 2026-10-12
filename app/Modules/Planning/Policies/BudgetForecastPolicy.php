<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Planning\Models\BudgetForecast;
use App\Modules\Identity\Models\User;

class BudgetForecastPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BudgetForecast $budgetForecast): bool
    {
        return $user->id === $budgetForecast->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BudgetForecast $budgetForecast): bool
    {
        return $user->id === $budgetForecast->user_id;
    }

    public function delete(User $user, BudgetForecast $budgetForecast): bool
    {
        return $user->id === $budgetForecast->user_id;
    }
}
