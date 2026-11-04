<?php

namespace App\Modules\Planning\Models;

use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the virtual money ledger: a signed amount moved into (or out of) a bucket.
 */
#[Fillable(['bucket_id', 'amount', 'currency', 'date', 'source', 'source_id', 'description'])]
class Allocation extends Model
{
    use BelongsToUser, HasFactory;

    /**
     * A bucket counts as funded from its earliest allocation on. Saving one dated before the stored date moves
     * it earlier; nothing moves it later (see BucketLedger::fundedSince).
     */
    protected static function booted(): void
    {
        static::saved(function (Allocation $allocation) {
            $date = $allocation->date->toDateString();

            Bucket::forUser($allocation->user_id)
                ->whereKey($allocation->bucket_id)
                ->where(fn ($query) => $query->whereNull('funded_since')->orWhere('funded_since', '>', $date))
                ->update(['funded_since' => $date]);
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => AsMoney::class,
            'date' => 'date',
            'source' => AllocationSource::class,
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }
}
