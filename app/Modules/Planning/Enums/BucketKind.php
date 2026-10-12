<?php

namespace App\Modules\Planning\Enums;

enum BucketKind: string
{
    case Spending = 'spending';
    case Savings = 'savings';

    public function label(): string
    {
        return __('enums.bucket_kind.'.$this->value);
    }
}
