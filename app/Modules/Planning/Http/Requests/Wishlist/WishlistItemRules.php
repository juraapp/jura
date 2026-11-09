<?php

namespace App\Modules\Planning\Http\Requests\Wishlist;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Project;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Validation shared by creating and updating a wish-list item.
 */
trait WishlistItemRules
{
    /**
     * @return array<string, mixed>
     */
    protected function itemRules(): array
    {
        $userId = $this->user()->id;
        $currency = strtoupper((string) $this->input('currency', $this->user()->currency_default));

        return [
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', ...MoneyRules::positive()],
            'currency' => ['nullable', 'string', 'size:3'],
            'importance' => ['nullable', 'integer', 'between:1,5'],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('user_id', $userId),
                function (string $attribute, mixed $value, Closure $fail) use ($userId, $currency) {
                    $projectCurrency = Project::forUser($userId)->whereKey($value)->value('currency');

                    if ($projectCurrency !== null && $projectCurrency !== $currency) {
                        $fail(__('wishlist.project_currency_mismatch', ['currency' => $projectCurrency]));
                    }
                },
            ],
            'bucket_id' => [
                Rule::prohibitedIf(fn () => $this->boolean('new_bucket')),
                'nullable',
                'integer',
                Rule::exists('buckets', 'id')->where('user_id', $userId)->where('kind', BucketKind::Savings->value)->where('currency', $currency),
            ],
            'new_bucket' => ['nullable', 'boolean'],
            'new_bucket_fill_rule' => [
                Rule::requiredIf(fn () => $this->boolean('new_bucket')),
                'nullable',
                Rule::in([FillRule::Fixed->value, FillRule::Percent->value, FillRule::PercentOfRemaining->value]),
            ],
            'new_bucket_fill_value' => [
                Rule::requiredIf(fn () => $this->boolean('new_bucket')),
                'nullable',
                'numeric',
                'gt:0',
                ...($this->input('new_bucket_fill_rule') === FillRule::Fixed->value ? ['decimal:0,2', 'max:'.MoneyRules::MAX] : ['decimal:0,4', 'max:100']),
            ],
            'desired_by' => ['nullable', 'date'],
            'allowed_account_ids' => ['nullable', 'array'],
            'allowed_account_ids.*' => [
                'integer',
                LedgerRules::account($userId),
                fn (string $attribute, mixed $value, Closure $fail) => app(LedgerQuery::class)->accountCurrency($userId, (int) $value) !== $currency
                    ? $fail(__('wishlist.currency_mismatch'))
                    : null,
            ],
            'url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
