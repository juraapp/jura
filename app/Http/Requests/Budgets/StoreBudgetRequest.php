<?php

namespace App\Http\Requests\Budgets;

use App\Enums\BudgetPeriodType;
use App\Models\Budget;
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period_type' => ['required', Rule::enum(BudgetPeriodType::class)],
        ];
    }
}
