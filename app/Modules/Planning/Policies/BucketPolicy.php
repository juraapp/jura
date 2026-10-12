<?php

namespace App\Modules\Planning\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Models\Bucket;

class BucketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Bucket $bucket): bool
    {
        return $user->id === $bucket->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Bucket $bucket): bool
    {
        return $user->id === $bucket->user_id;
    }

    public function delete(User $user, Bucket $bucket): bool
    {
        return $user->id === $bucket->user_id;
    }
}
