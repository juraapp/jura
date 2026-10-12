<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use Illuminate\Validation\Rule;

class BulkUpdateTransactionsRequest extends BulkTransactionsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return parent::rules() + [
            'category_id' => [
                'nullable', 'integer', 'required_without:account_id',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id')),
            ],
            'account_id' => ['nullable', 'integer', 'required_without:category_id', Rule::exists('accounts', 'id')->where('user_id', $userId)],
        ];
    }
}
