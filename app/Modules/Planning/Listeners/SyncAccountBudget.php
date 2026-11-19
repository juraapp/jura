<?php

namespace App\Modules\Planning\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Events\AccountBudgetChanged;
use App\Modules\Planning\Services\ExpenseSpending;
use App\Modules\Planning\Services\WishlistService;
use Illuminate\Support\Facades\DB;

/**
 * An account left the budget (off budget or archived) or came back: its expenses paid from a bucket stop coming out
 * of that bucket, or come out of it again. Its category spending follows the same rule through the ledger query, so
 * an account outside the budget touches no envelope at all.
 */
class SyncAccountBudget
{
    public function __construct(
        private readonly LedgerQuery $ledger,
        private readonly ExpenseSpending $spending,
        private readonly WishlistService $wishlist,
    ) {}

    public function handle(AccountBudgetChanged $event): void
    {
        $user = User::find($event->userId);
        if ($user === null) {
            return;
        }

        $expenses = $this->ledger->bucketExpenses($user->id, $event->accountId);

        DB::transaction(function () use ($user, $event, $expenses) {
            foreach ($expenses as $expense) {
                $event->inBudget
                    ? $this->spending->apply($user, $expense['id'], $event->accountId, $expense['amount'], $expense['date'], $expense['bucket_id'])
                    : $this->spending->remove($user, $expense['id'], $expense['bucket_id']);
            }

            $this->wishlist->accountBudgetChanged($user, $expenses->pluck('amount', 'id')->all(), $event->inBudget);
        });
    }
}
