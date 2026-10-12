<?php

namespace App\Modules\Planning\Http\Requests\Budgets;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Budget::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->orWhereNull('user_id'))],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period_type' => ['required', Rule::enum(BudgetPeriodType::class)],
        ];
    }
}
