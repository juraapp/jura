<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use App\Modules\Shared\Validation\LedgerRules;

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
                LedgerRules::category($userId),
            ],
            'account_id' => ['nullable', 'integer', 'required_without:category_id', LedgerRules::account($userId, activeOnly: true)],
        ];
    }
}
