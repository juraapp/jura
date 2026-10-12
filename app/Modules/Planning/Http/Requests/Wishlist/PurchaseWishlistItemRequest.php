<?php

namespace App\Modules\Planning\Http\Requests\Wishlist;

use App\Modules\Ledger\Contracts\LedgerQuery;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('wishlistItem'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('type', 'expense')->where(fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id'))],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
        ];
    }

    /**
     * The account must match the item's currency and be allowed by both the item and its bucket.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('account_id')) {
                return;
            }

            $item = $this->route('wishlistItem');
            $accountId = (int) $this->input('account_id');

            $currency = app(LedgerQuery::class)->accountCurrency($accountId);
            if (strtoupper((string) $currency) !== $item->currency) {
                $validator->errors()->add('account_id', __('wishlist.currency_mismatch'));

                return;
            }

            if (! $item->acceptsAccount($accountId) || ($item->bucket !== null && ! $item->bucket->acceptsAccount($accountId))) {
                $validator->errors()->add('account_id', __('wishlist.account_not_allowed'));
            }
        }];
    }
}
