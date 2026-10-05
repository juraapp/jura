<?php

namespace App\Models;

use App\Casts\AsMoney;
use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'account_id', 'category_id', 'type', 'amount', 'description',
    'frequency', 'next_due_date', 'day_of_month', 'is_active', 'end_date',
])]
class RecurringTransaction extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => AsMoney::class,
            'frequency' => RecurringFrequency::class,
            'next_due_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
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

    public function generatedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isDue(): bool
    {
        return $this->is_active
            && $this->next_due_date->lte(now())
            && ($this->end_date === null || $this->end_date->gte(now()));
    }
}
