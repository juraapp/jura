<?php

namespace App\Modules\Planning\Http\Requests\Planner;

class SuggestScenarioRequest extends PlannerRunRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->runRules() + [
            'item_ids' => ['required', 'array', 'min:1', 'max:12'],
            'item_ids.*' => $this->itemRule(),
        ];
    }
}
