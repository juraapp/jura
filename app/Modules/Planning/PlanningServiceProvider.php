<?php

namespace App\Modules\Planning;

use App\Modules\Planning\Contracts\BudgetProgress;
use App\Modules\Planning\Services\BudgetService;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BudgetProgress::class, BudgetService::class);
    }
}
