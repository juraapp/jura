<?php

namespace App\Modules\Planning\Models;

use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'series_id', 'type', 'description', 'amount', 'currency', 'date', 'account_id',
    'category_id', 'certainty', 'status', 'realized_transaction_id', 'allocation_overrides', 'notes',
])]
class PlannedEvent extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => AsMoney::class,
            'date' => 'date',
            'certainty' => Certainty::class,
            'status' => PlannedEventStatus::class,
            'allocation_overrides' => 'array',
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PlannedEventStatus::Planned->value);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(PlannedEventSeries::class, 'series_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function realizedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'realized_transaction_id');
    }

    public function isIncome(): bool
    {
        return $this->type === TransactionType::Income;
    }
}
