<?php

namespace App\Modules\Planning\Models;

use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id', 'bucket_id', 'name', 'price', 'currency', 'importance', 'desired_by', 'allowed_account_ids',
    'status', 'purchased_on', 'purchased_transaction_id', 'purchase_allocation_id', 'url', 'notes',
])]
class WishlistItem extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'price' => AsMoney::class,
            'importance' => 'integer',
            'desired_by' => 'date',
            'purchased_on' => 'date',
            'allowed_account_ids' => 'array',
            'status' => WishlistStatus::class,
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function purchasedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'purchased_transaction_id');
    }

    public function isWanted(): bool
    {
        return $this->status === WishlistStatus::Wanted;
    }

    /**
     * Whether this item may be paid for with the account (no restriction = any account).
     */
    public function acceptsAccount(int $accountId): bool
    {
        $allowed = $this->allowed_account_ids;

        return empty($allowed) || in_array($accountId, $allowed, true);
    }
}
