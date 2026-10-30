<?php

namespace App\Modules\Planning\Http\Requests\Wishlist;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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
            'account_id' => ['required', 'integer', LedgerRules::account($userId, activeOnly: true)],
            'category_id' => ['required', 'integer', LedgerRules::category($userId)->where('type', 'expense')],
            'amount' => ['nullable', ...MoneyRules::positive()],
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

            $currency = app(LedgerQuery::class)->accountCurrency($this->user()->id, $accountId);
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
