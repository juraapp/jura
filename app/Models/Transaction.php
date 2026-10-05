<?php

namespace App\Models;

use App\Casts\AsMoney;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'account_id', 'type', 'category_id', 'amount', 'date', 'description',
    'notes', 'payment_method', 'is_recurring_generated', 'recurring_transaction_id',
    'transfer_group_id',
])]
class Transaction extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => AsMoney::class,
            'date' => 'date',
            'is_recurring_generated' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    public function scopeOfType(Builder $query, TransactionType|string $type): Builder
    {
        return $query->where('type', $type instanceof TransactionType ? $type->value : $type);
    }

    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * "Real" income and expenses — excludes both legs of a transfer, which
     * should never count as an income/expense movement.
     */
    public function scopeCashFlow(Builder $query): Builder
    {
        return $query->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value]);
    }
}
