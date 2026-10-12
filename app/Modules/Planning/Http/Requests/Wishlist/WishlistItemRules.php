<?php

namespace App\Modules\Planning\Http\Requests\Wishlist;

use App\Modules\Planning\Enums\BucketKind;
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
            'price' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'importance' => ['nullable', 'integer', 'between:1,5'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'bucket_id' => [
                'nullable',
                'integer',
                Rule::exists('buckets', 'id')->where('user_id', $userId)->where('kind', BucketKind::Savings->value)->where('currency', $currency),
            ],
            'desired_by' => ['nullable', 'date'],
            'allowed_account_ids' => ['nullable', 'array'],
            'allowed_account_ids.*' => ['integer', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
