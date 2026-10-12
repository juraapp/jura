<?php

namespace App\Modules\Planning\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wishlist_item_id', 'position', 'not_before'])]
class ScenarioLine extends Model
{
    protected function casts(): array
    {
        return ['not_before' => 'date', 'position' => 'integer'];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WishlistItem::class, 'wishlist_item_id');
    }
}
