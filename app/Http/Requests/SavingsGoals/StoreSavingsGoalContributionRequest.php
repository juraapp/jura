<?php

namespace App\Http\Requests\SavingsGoals;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsGoalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('savingsGoal'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
        ];
    }
}
