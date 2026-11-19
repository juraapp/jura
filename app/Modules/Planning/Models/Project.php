<?php

namespace App\Modules\Planning\Models;

use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group for wish-list items: a project, a hobby, a category of wants. It has one currency, may have a budget and a
 * target date, and may have a savings bucket of its own that pays for its items (unless an item has a bucket).
 */
#[Fillable(['name', 'kind', 'currency', 'budget_amount', 'target_date', 'bucket_id', 'color', 'icon', 'notes'])]
class Project extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => ProjectKind::class,
            'budget_amount' => AsMoney::class,
            'target_date' => 'date',
        ];
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }
}
