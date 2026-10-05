<?php

namespace App\Models;

use App\Casts\AsMoney;
use App\Enums\AccountType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'type', 'institution', 'initial_balance', 'currency',
    'color', 'icon', 'masked_number', 'is_active', 'notes',
])]
class Account extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance' => AsMoney::class,
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }
}
