<?php

namespace App\Http\Requests\SavingsGoals;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('savingsGoal'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'target_date' => ['nullable', 'date', 'after:today'],
            'icon' => ['required', 'string', 'max:32'],
            'color' => ['required', 'string', 'max:7'],
        ];
    }
}
