<?php

namespace App\Modules\Planning\Enums;

enum PlannedEventStatus: string
{
    case Planned = 'planned';
    case Realized = 'realized';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('enums.planned_event_status.'.$this->value);
    }
}
