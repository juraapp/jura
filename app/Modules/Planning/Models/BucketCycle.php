<?php

namespace App\Modules\Planning\Models;

use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cycle of a bucket. The open one only carries per-cycle overrides; a closed one is
 * an immutable snapshot of how the bucket filled and emptied (opening + filled + moved in
 * - moved out - spent = what was left, of which `released` went on to another bucket).
 */
#[Fillable([
    'bucket_id', 'starts_on', 'ends_on', 'status', 'opening', 'filled', 'moved_in', 'moved_out',
    'spent', 'released', 'closing', 'cap', 'cap_override', 'closed_at',
])]
class BucketCycle extends Model
{
    use BelongsToUser, HasFactory;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'opening' => AsMoney::class,
            'filled' => AsMoney::class,
            'moved_in' => AsMoney::class,
            'moved_out' => AsMoney::class,
            'spent' => AsMoney::class,
            'released' => AsMoney::class,
            'closing' => AsMoney::class,
            'cap' => AsMoney::class,
            'cap_override' => AsMoney::class,
            'closed_at' => 'datetime',
        ];
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }
}
