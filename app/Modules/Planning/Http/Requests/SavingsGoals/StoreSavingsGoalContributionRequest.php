<?php

namespace App\Modules\Planning\Http\Requests\SavingsGoals;

use App\Modules\Shared\Validation\MoneyRules;
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
            'amount' => ['required', ...MoneyRules::positive()],
            'date' => ['required', 'date'],
        ];
    }
}
