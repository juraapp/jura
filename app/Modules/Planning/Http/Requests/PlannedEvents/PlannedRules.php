<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Validation shared by creating and editing a plan or a repeating plan. A plan that records itself
 * automatically needs an active account and a category; a planned transfer goes from account_id to a different
 * active counter_account_id in the same currency and has no category; an income's bucket split is kept only for incomes.
 */
trait PlannedRules
{
    /**
     * @return array<string, mixed>
     */
    protected function plannedRules(): array
    {
        $userId = $this->user()->id;
        $autoPost = $this->boolean('auto_post');
        $transfer = $this->input('type') === 'transfer';

        return [
            'type' => ['required', 'in:income,expense,transfer'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', ...MoneyRules::positive()],
            'currency' => ['nullable', 'string', 'size:3'],
            'account_id' => [Rule::requiredIf($autoPost || $transfer), 'nullable', 'integer', LedgerRules::account($userId, activeOnly: $autoPost || $transfer)],
            'counter_account_id' => [
                'exclude_unless:type,transfer',
                'required',
                'integer',
                'different:account_id',
                LedgerRules::account($userId, activeOnly: true),
                fn (string $attribute, mixed $value, Closure $fail) => app(LedgerQuery::class)->accountCurrency($userId, (int) $value) !== app(LedgerQuery::class)->accountCurrency($userId, (int) $this->input('account_id'))
                    ? $fail(__('transactions.currency_mismatch'))
                    : null,
            ],
            'category_id' => [Rule::requiredIf($autoPost && ! $transfer), Rule::prohibitedIf($transfer), 'nullable', 'integer', LedgerRules::category($userId, (string) $this->input('type'))],
            'certainty' => ['required', 'in:confirmed,expected,speculative'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'auto_post' => ['sometimes', 'boolean'],
            'allocation_overrides' => ['exclude_unless:type,income', 'nullable', 'array'],
            'allocation_overrides.*.bucket_id' => ['exclude_unless:type,income', 'required', 'integer', Rule::exists('buckets', 'id')->where('user_id', $userId)],
            'allocation_overrides.*.amount' => ['exclude_unless:type,income', 'required', ...MoneyRules::positive()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function plannedMessages(): array
    {
        return [
            'account_id.required' => __('planned-events.auto_post_requires'),
            'category_id.required' => __('planned-events.auto_post_requires'),
        ];
    }
}
