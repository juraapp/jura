<?php

namespace App\Modules\Planning\Http\Requests\Planner;

class SimulateScenarioRequest extends PlannerRunRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->runRules() + [
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.item_id' => $this->itemRule(),
            'lines.*.not_before' => ['nullable', 'date'],
        ];
    }
}
