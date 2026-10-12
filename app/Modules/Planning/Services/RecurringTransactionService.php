<?php

namespace App\Modules\Planning\Services;

use App\Modules\Planning\Models\RecurringTransaction;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use Illuminate\Support\Facades\DB;

/**
 * Materializes due recurring templates into real transactions. Runs inside
 * the user's own timezone so "due tomorrow" matches what they'd expect, not
 * the server's UTC clock.
 */
class RecurringTransactionService
{
    public function __construct(private readonly RecordsTransactions $transactions) {}

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

            $this->transactions->recordRecurring($recurring->user, $recurring->type, $data, $recurring->id);

            $nextDue = $recurring->frequency->addTo($recurring->next_due_date);

            $recurring->update([
                'next_due_date' => $nextDue,
                'is_active' => $recurring->end_date === null || $nextDue->lte($recurring->end_date),
            ]);
        });
    }
}
