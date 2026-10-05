<?php

namespace App\Policies;

use App\Models\BudgetForecast;
use App\Models\User;

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
