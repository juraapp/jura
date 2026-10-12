<?php

namespace App\Modules\Planning\Enums;

enum ProjectKind: string
{
    case Project = 'project';
    case Hobby = 'hobby';
    case Category = 'category';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.project_kind.'.$this->value);
    }
}
