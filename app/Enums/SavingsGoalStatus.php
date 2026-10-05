<?php

namespace App\Enums;

enum SavingsGoalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return __('enums.savings_goal_status.'.$this->value);
    }
}
