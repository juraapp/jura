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
