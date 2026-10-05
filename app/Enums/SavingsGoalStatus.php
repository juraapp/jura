<?php

namespace App\Enums;

enum SavingsGoalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Completed => __('Completed'),
            self::Archived => __('Archived'),
        };
    }
}
