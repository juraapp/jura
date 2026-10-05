<?php

namespace App\Models;

use App\Casts\AsMoney;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_id', 'category_id', 'description', 'amount', 'date', 'is_anticipated', 'currency', 'notes'])]
class BudgetForecast extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => AsMoney::class,
            'date' => 'date',
            'is_anticipated' => 'boolean',
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
