<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return $category->user_id === null || $user->id === $category->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $category): bool
    {
        return $category->isEditable() && $user->id === $category->user_id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $category->isEditable() && $user->id === $category->user_id;
    }
}
