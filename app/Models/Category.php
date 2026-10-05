<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'type', 'parent_id', 'icon', 'color', 'is_system'])]
class Category extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Categories are visible to a user when they own them OR when they are a
     * system seed (user_id null) — unlike other models, "belongs to user" here
     * means "scoped to user OR shared", so this doesn't use BelongsToUser.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('visibleToUser', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where(function (Builder $query) {
                    $query->where('user_id', Auth::id())->orWhereNull('user_id');
                });
            }
        });

        static::creating(function (Category $category) {
            if (! $category->isDirty('user_id') && Auth::check()) {
                $category->user_id = Auth::id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'is_system' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isEditable(): bool
    {
        return ! $this->is_system;
    }
}
