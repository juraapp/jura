<?php

namespace App\Modules\Planning\Http\Requests\Planner;

use App\Modules\Planning\Models\Scenario;

class StoreScenarioRequest extends PlannerRunRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Scenario::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->runRules() + [
            'name' => ['required', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.item_id' => $this->itemRule(),
            'lines.*.not_before' => ['nullable', 'date'],
        ];
    }
}
