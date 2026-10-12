<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Models\WishlistItem;

class WishlistItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WishlistItem $wishlistItem): bool
    {
        return $user->id === $wishlistItem->user_id;
    }

    public function delete(User $user, WishlistItem $wishlistItem): bool
    {
        return $user->id === $wishlistItem->user_id;
    }
}
