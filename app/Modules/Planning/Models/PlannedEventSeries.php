<?php

namespace App\Modules\Planning\Models;

use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'description', 'amount', 'currency', 'account_id', 'category_id',
    'certainty', 'frequency', 'starts_on', 'ends_on',
])]
class PlannedEventSeries extends Model
{
    use BelongsToUser, HasFactory;

    protected $table = 'planned_event_series';

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => AsMoney::class,
            'certainty' => Certainty::class,
            'frequency' => RecurringFrequency::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlannedEvent::class, 'series_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
