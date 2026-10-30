<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Closure;
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
        // A fixed fill is money (2 decimals); a percentage keeps up to 4.
        $moneyWhenFixed = fn (string $attribute, mixed $value, Closure $fail) => $this->input('fill_rule') === FillRule::Fixed->value
            && is_numeric($value) && preg_match('/^\d+(\.\d{1,2})?$/', (string) $value) !== 1
                ? $fail(__('validation.decimal', ['decimal' => '0-2']))
                : null;

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
            'currency' => [
                'required',
                'string',
                'size:3',
                fn (string $attribute, mixed $value, Closure $fail) => $editing !== null
                    && strtoupper((string) $value) !== $editing->currency
                    && $editing->allocations()->exists()
                        ? $fail(__('buckets.currency_locked'))
                        : null,
            ],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'category_id' => [
                Rule::requiredIf($spending),
                'nullable',
                'integer',
                LedgerRules::category($userId, 'expense'),
                ...($spending() && $editing !== null ? [$categoryUnique] : []),
            ],
            'cycle' => [Rule::requiredIf($spending), 'nullable', Rule::enum(BudgetPeriodType::class)],
            'cycle_every' => ['nullable', 'integer', 'between:1,366'],
            'cycle_anchor' => ['nullable', 'date'],
            'cycle_goal' => ['nullable', ...MoneyRules::positive()],
            'cap_amount' => [Rule::requiredIf(fn () => $spending() || $needsTarget()), 'nullable', ...MoneyRules::positive()],
            'fill_rule' => ['nullable', Rule::enum(FillRule::class)],
            'fill_value' => [
                Rule::requiredIf(fn () => in_array($this->input('fill_rule'), [FillRule::Fixed->value, FillRule::Percent->value, FillRule::PercentOfRemaining->value], true)),
                'nullable',
                'numeric',
                'decimal:0,4',
                'gt:0',
                'max:'.MoneyRules::MAX,
                fn ($attribute, $value, $fail) => in_array($this->input('fill_rule'), [FillRule::Percent->value, FillRule::PercentOfRemaining->value], true) && (float) $value > 100
                    ? $fail('The percentage cannot be more than 100.')
                    : null,
                $moneyWhenFixed,
            ],
            'step_up_value' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:'.MoneyRules::MAX, 'required_with:step_up_after_bucket_id', $moneyWhenFixed],
            'step_up_after_bucket_id' => [
                'nullable',
                'integer',
                'required_with:step_up_value',
                Rule::exists('buckets', 'id')->where('user_id', $userId)->where('currency', strtoupper((string) $this->input('currency')))->whereNotNull('cap_amount'),
                ...($editing !== null ? [Rule::notIn([$editing->id])] : []),
            ],
            'completes_on_purchase' => ['nullable', 'boolean'],
            'carry_over' => ['nullable', Rule::enum(CarryOverPolicy::class)],
            'overflow_bucket_id' => [
                Rule::requiredIf(fn () => $this->input('carry_over') === CarryOverPolicy::Overflow->value),
                'nullable',
                'integer',
                Rule::exists('buckets', 'id')->where('user_id', $userId)->where('currency', strtoupper((string) $this->input('currency'))),
                ...($editing !== null ? [Rule::notIn([$editing->id])] : []),
            ],
            'allowed_account_ids' => ['nullable', 'array'],
            'allowed_account_ids.*' => [
                'integer',
                LedgerRules::account($userId),
                fn (string $attribute, mixed $value, Closure $fail) => app(LedgerQuery::class)->accountCurrency($userId, (int) $value) !== strtoupper((string) $this->input('currency'))
                    ? $fail(__('buckets.account_currency_mismatch'))
                    : null,
            ],
            'opening_balance' => ['nullable', ...MoneyRules::nonNegative()],
        ];
    }
}
