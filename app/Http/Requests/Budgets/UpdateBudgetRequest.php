<?php

namespace App\Http\Requests\Budgets;

use App\Enums\BudgetPeriodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('budget'));
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
