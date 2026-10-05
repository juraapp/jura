<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Materializes due recurring templates into real transactions. Runs inside
 * the user's own timezone so "due tomorrow" matches what they'd expect, not
 * the server's UTC clock.
 */
class RecurringTransactionService
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function generateDue(): int
    {
        $generated = 0;

        RecurringTransaction::with('user')
            ->where('is_active', true)
            ->where('next_due_date', '<=', now()->toDateString())
            ->chunkById(50, function ($recurrences) use (&$generated) {
                foreach ($recurrences as $recurring) {
                    $this->generateOne($recurring);
                    $generated++;
                }
            });

        return $generated;
    }

    private function generateOne(RecurringTransaction $recurring): void
    {
        DB::transaction(function () use ($recurring) {
            $data = [
                'account_id' => $recurring->account_id,
                'category_id' => $recurring->category_id,
                'amount' => $recurring->amount,
                'date' => $recurring->next_due_date,
                'description' => $recurring->description,
            ];

            $transaction = $recurring->type === TransactionType::Income
                ? $this->transactions->createIncome($recurring->user, $data)
                : $this->transactions->createExpense($recurring->user, $data);

            $transaction->update([
                'is_recurring_generated' => true,
                'recurring_transaction_id' => $recurring->id,
            ]);

            $nextDue = $recurring->frequency->addTo($recurring->next_due_date);

            $recurring->update([
                'next_due_date' => $nextDue,
                'is_active' => $recurring->end_date === null || $nextDue->lte($recurring->end_date),
            ]);
        });
    }
}
