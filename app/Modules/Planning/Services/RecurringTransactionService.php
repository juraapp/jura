<?php

namespace App\Modules\Planning\Services;

use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Planning\Models\RecurringTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Materializes due recurring templates into real transactions. Runs for every
 * user at once (hourly job; a template is due once its date is today or earlier in its owner's timezone), so one template that can't be generated is logged
 * and skipped instead of stopping the rest of the batch.
 */
class RecurringTransactionService
{
    public function __construct(private readonly RecordsTransactions $transactions) {}

    public function generateDue(): int
    {
        $generated = 0;

        RecurringTransaction::withoutGlobalScope('user')
            ->with([
                'user',
                'account' => fn ($query) => $query->withoutGlobalScope('user'),
                'category' => fn ($query) => $query->withoutGlobalScope('visibleToUser')->withTrashed(),
            ])
            ->where('is_active', true)
            ->where('next_due_date', '<=', now()->addDay()->toDateString())
            ->chunkById(50, function ($recurrences) use (&$generated) {
                foreach ($recurrences as $recurring) {
                    if ($recurring->user !== null && $recurring->next_due_date->gt($recurring->user->today())) {
                        continue;
                    }

                    if ($this->tryGenerate($recurring)) {
                        $generated++;
                    }
                }
            });

        return $generated;
    }

    private function tryGenerate(RecurringTransaction $recurring): bool
    {
        $problem = match (true) {
            $recurring->user === null => 'its user is missing',
            $recurring->account === null => 'its account is deleted or missing',
            ! $recurring->account->is_active => 'its account is archived',
            $recurring->category === null || $recurring->category->trashed() => 'its category is deleted or missing',
            default => null,
        };

        if ($problem !== null) {
            Log::warning("Skipped a recurring transaction because {$problem}.", [
                'recurring_transaction_id' => $recurring->id,
                'account_id' => $recurring->account_id,
                'category_id' => $recurring->category_id,
            ]);

            return false;
        }

        try {
            return $this->generateOne($recurring);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Records the occurrence that is due and moves the template on, unless another run already did: the row is
     * locked and its due date checked again inside the transaction.
     *
     * @return bool whether a transaction was recorded
     */
    private function generateOne(RecurringTransaction $recurring): bool
    {
        return DB::transaction(function () use ($recurring) {
            $current = RecurringTransaction::withoutGlobalScope('user')->lockForUpdate()->find($recurring->id);

            if ($current === null || ! $current->is_active || ! $current->next_due_date->equalTo($recurring->next_due_date)) {
                return false;
            }

            $data = [
                'account_id' => $recurring->account_id,
                'category_id' => $recurring->category_id,
                'amount' => $recurring->amount,
                'date' => $recurring->next_due_date,
                'description' => $recurring->description,
            ];

            $this->transactions->recordRecurring($recurring->user, $recurring->type, $data, $recurring->id);

            $nextDue = $recurring->frequency->addTo($recurring->next_due_date);

            $current->update([
                'next_due_date' => $nextDue,
                'is_active' => $recurring->end_date === null || $nextDue->lte($recurring->end_date),
            ]);

            return true;
        });
    }
}
