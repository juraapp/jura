<?php

namespace App\Modules\Planning\Models;

use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Shared\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A saved purchase order (the planner's "lines") to compare against others.
 */
#[Fillable(['name', 'currency', 'forecast_view', 'horizon_months'])]
class Scenario extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'forecast_view' => ForecastView::class,
            'horizon_months' => 'integer',
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ScenarioLine::class)->orderBy('position');
    }
}
