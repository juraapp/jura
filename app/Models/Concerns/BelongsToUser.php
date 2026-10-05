<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes every query to the authenticated user and auto-fills user_id on
 * create, so two Eloquent-layer users can never see or mutate each other's
 * financial data even if a Policy check is accidentally skipped somewhere.
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable().'.user_id', Auth::id());
            }
        });

        static::creating(function ($model) {
            if (! $model->user_id && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Explicit scope for contexts without an authenticated request (jobs, console).
     */
    public function scopeForUser(Builder $query, Authenticatable|int $user): Builder
    {
        $userId = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        return $query->withoutGlobalScope('user')->where($query->getModel()->getTable().'.user_id', $userId);
    }
}
