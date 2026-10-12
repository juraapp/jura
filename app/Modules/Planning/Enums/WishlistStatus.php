<?php

namespace App\Modules\Planning\Enums;

enum WishlistStatus: string
{
    case Wanted = 'wanted';
    case Purchased = 'purchased';
    case Dropped = 'dropped';

    public function label(): string
    {
        return __('enums.wishlist_status.'.$this->value);
    }
}
