<?php

namespace App\Modules\Planning\Http\Requests\Planner;

use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for anything that runs the planner: which currency, which view of the future, how far ahead.
 */
abstract class PlannerRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', WishlistItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function runRules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3'],
            'view' => ['required', Rule::enum(ForecastView::class)],
            'horizon_months' => ['required', 'integer', 'in:12,24,36'],
        ];
    }

    /**
     * An existing, still-wanted item of the user's in the planner's currency.
     *
     * @return list<mixed>
     */
    protected function itemRule(): array
    {
        return [
            'required',
            'integer',
            Rule::exists('wishlist_items', 'id')
                ->where('user_id', $this->user()->id)
                ->where('status', WishlistStatus::Wanted->value)
                ->where('currency', strtoupper((string) $this->input('currency'))),
        ];
    }
}
