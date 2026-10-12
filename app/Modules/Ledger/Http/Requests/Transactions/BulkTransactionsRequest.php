<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use App\Modules\Ledger\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Picks the transactions a bulk action applies to: explicit ids, or everything the
 * current list filters match.
 */
class BulkTransactionsRequest extends FormRequest
{
    public const MAX_IDS = 500;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Transaction::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required_without:all_matching', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer'],
            'all_matching' => ['nullable', 'boolean'],
            'type' => ['nullable', 'in:all,income,expense,transfer'],
            'filters' => ['nullable', 'array'],
            'filters.account' => ['nullable', 'integer'],
            'filters.category' => ['nullable', 'integer'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date'],
            'filters.q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
