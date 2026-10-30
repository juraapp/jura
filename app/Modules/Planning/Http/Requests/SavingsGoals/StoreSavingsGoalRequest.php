<?php

namespace App\Modules\Planning\Http\Requests\SavingsGoals;

use App\Modules\Planning\Models\SavingsGoal;
use App\Modules\Shared\Validation\MoneyRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SavingsGoal::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', ...MoneyRules::positive()],
            'currency' => ['required', 'string', 'size:3'],
            'target_date' => ['nullable', 'date', 'after:'.$this->user()->today()->toDateString()],
            'icon' => ['required', 'string', 'max:32'],
            'color' => ['required', 'string', 'max:7'],
        ];
    }
}
