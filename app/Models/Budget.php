<?php

namespace App\Models;

use App\Casts\AsMoney;
use App\Enums\BudgetPeriodType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'currency', 'amount', 'allocated_amount', 'period_type', 'period_start'])]
class Budget extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => AsMoney::class,
            'allocated_amount' => AsMoney::class,
            'period_type' => BudgetPeriodType::class,
            'period_start' => 'date',
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(BudgetForecast::class);
    }
}
