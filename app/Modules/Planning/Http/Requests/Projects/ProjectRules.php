<?php

namespace App\Modules\Planning\Http\Requests\Projects;

use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Planning\Models\Project;
use App\Modules\Shared\Validation\MoneyRules;
use Illuminate\Validation\Rule;

/**
 * Validation shared by creating and updating a project.
 *
 * A project has one currency. Its savings bucket is one of the user's savings buckets in that currency that no
 * other project uses (`bucket_mode` = existing), or a new one made for it (`bucket_mode` = new, filled by
 * `new_bucket`), or none.
 */
trait ProjectRules
{
    /**
     * @return array<string, mixed>
     */
    protected function projectRules(?Project $editing = null): array
    {
        $userId = $this->user()->id;
        $newFill = fn () => $this->input('bucket_mode') === 'new';
        $money = in_array($this->input('new_bucket.fill_rule'), [FillRule::Fixed->value], true);

        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(ProjectKind::class)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'currency' => [
                Rule::requiredIf(fn () => $this->filled('budget_amount') || in_array($this->input('bucket_mode'), ['existing', 'new'], true)),
                'nullable', 'string', 'size:3',
            ],
            'budget_amount' => ['nullable', ...MoneyRules::positive()],
            'target_date' => ['nullable', 'date'],
            'bucket_mode' => ['nullable', Rule::in(['none', 'existing', 'new'])],
            'bucket_id' => [
                Rule::requiredIf(fn () => $this->input('bucket_mode') === 'existing'),
                'nullable', 'integer',
                Rule::exists('buckets', 'id')
                    ->where('user_id', $userId)
                    ->where('kind', BucketKind::Savings->value)
                    ->where('currency', strtoupper((string) $this->input('currency'))),
                Rule::unique('projects', 'bucket_id')->ignore($editing?->id),
            ],
            'new_bucket' => ['nullable', 'array'],
            'new_bucket.fill_rule' => [Rule::requiredIf($newFill), 'nullable', Rule::in([FillRule::Fixed->value, FillRule::Percent->value, FillRule::PercentOfRemaining->value])],
            'new_bucket.fill_value' => [
                Rule::requiredIf(fn () => $newFill() && $this->filled('new_bucket.fill_rule')),
                'nullable', 'numeric', 'gt:0',
                ...($money ? ['decimal:0,2', 'max:'.MoneyRules::MAX] : ['decimal:0,4', 'max:100']),
            ],
        ];
    }
}
