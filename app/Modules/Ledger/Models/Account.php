<?php

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Enums\YieldCompounding;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'type', 'institution', 'initial_balance', 'currency',
    'color', 'icon', 'masked_number', 'is_active', 'on_budget', 'notes', 'yield_rate', 'yield_compounding',
])]
class Account extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance' => AsMoney::class,
            'yield_rate' => 'decimal:4',
            'yield_compounding' => YieldCompounding::class,
            'is_active' => 'boolean',
            'on_budget' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function plannedEventSeries(): HasMany
    {
        return $this->hasMany(PlannedEventSeries::class);
    }

    /**
     * Whether an active repeating plan still uses this account; deleting it then archives it instead.
     */
    public function isUsedByActiveSeries(): bool
    {
        return $this->plannedEventSeries()->withoutGlobalScope('user')->where('is_active', true)->exists();
    }

    /**
     * Whether the account's currency can no longer change: it has transactions (trashed ones included), or a
     * planned event or series, or a bucket's or wish-list item's allowed accounts use it.
     */
    public function isCurrencyLocked(): bool
    {
        $planned = fn (string $model) => $model::forUser($this->user_id)->where('account_id', $this->id)->exists();
        $allows = fn (string $model) => $model::forUser($this->user_id)->whereJsonContains('allowed_account_ids', $this->id)->exists();

        return $this->transactions()->withTrashed()->exists()
            || $planned(PlannedEvent::class)
            || $planned(PlannedEventSeries::class)
            || $allows(Bucket::class)
            || $allows(WishlistItem::class);
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }
}
