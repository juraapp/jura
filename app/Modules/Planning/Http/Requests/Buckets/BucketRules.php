<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Validation\Rule;

/**
 * Validation shared by creating and updating a bucket.
 */
trait BucketRules
{
    /**
     * @return array<string, mixed>
     */
    protected function bucketRules(?Bucket $editing = null): array
    {
        $userId = $this->user()->id;
        $spending = fn () => $this->input('kind') === BucketKind::Spending->value;
        $needsTarget = fn () => $this->input('fill_rule') === FillRule::FillToTarget->value;

        $categoryUnique = Rule::unique('buckets', 'category_id')
            ->where('user_id', $userId)
            ->where('currency', strtoupper((string) $this->input('currency')))
            ->where('cycle', $this->input('cycle'));
        if ($editing !== null) {
            $categoryUnique->ignore($editing->id);
        }

        return [
            'kind' => ['required', Rule::enum(BucketKind::class)],
            'name' => [Rule::requiredIf(fn () => ! $spending()), 'nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'category_id' => [
                Rule::requiredIf($spending),
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id')),
                ...($spending() && $editing !== null ? [$categoryUnique] : []),
            ],
            'cycle' => [Rule::requiredIf($spending), 'nullable', Rule::enum(BudgetPeriodType::class)],
            'cap_amount' => [Rule::requiredIf(fn () => $spending() || $needsTarget()), 'nullable', 'numeric', 'gt:0'],
            'fill_rule' => ['nullable', Rule::enum(FillRule::class)],
            'fill_value' => [
                Rule::requiredIf(fn () => in_array($this->input('fill_rule'), [FillRule::Fixed->value, FillRule::Percent->value], true)),
                'nullable',
                'numeric',
                'gt:0',
                fn ($attribute, $value, $fail) => $this->input('fill_rule') === FillRule::Percent->value && (float) $value > 100
                    ? $fail('The percentage cannot be more than 100.')
                    : null,
            ],
            'carry_over' => ['nullable', Rule::enum(CarryOverPolicy::class)],
            'overflow_bucket_id' => [
                Rule::requiredIf(fn () => $this->input('carry_over') === CarryOverPolicy::Overflow->value),
                'nullable',
                'integer',
                Rule::exists('buckets', 'id')->where('user_id', $userId)->where('currency', strtoupper((string) $this->input('currency'))),
                ...($editing !== null ? [Rule::notIn([$editing->id])] : []),
            ],
            'allowed_account_ids' => ['nullable', 'array'],
            'allowed_account_ids.*' => ['integer', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
