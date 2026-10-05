<?php

namespace App\Http\Requests\BudgetForecasts;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'is_anticipated' => ['required', 'boolean'],
            'currency' => ['required', 'string', 'size:3'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->orWhereNull('user_id'))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
