<?php

namespace App\Modules\Planning\Models;

use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group for wish-list items: a project, a hobby, a category of wants.
 */
#[Fillable(['name', 'kind', 'color', 'icon', 'notes'])]
class Project extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return ['kind' => ProjectKind::class];
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }
}
