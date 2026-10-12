<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Shared\Support\Money;
use App\Modules\Notifications\Notifications\LowAccountBalance;

/**
 * Flags an account as "low" when its balance can't cover ~3 days of the
 * user's recent average spending. Notifies once per low-balance episode —
 * skipped while an unread alert for that account is still in the bell.
 */
class CheckLowAccountBalance
{
    public function __construct(private readonly Balances $balances) {}

    public function handle(TransactionsMutated $event): void
    {
        $dailyAverages = [];

        foreach ($event->accounts as $account) {
            if (! $account->is_active) {
                continue;
            }

            $dailyAverage = $dailyAverages[$account->currency] ??= $this->trailingDailyExpenseAverage($event->user->id, $account->currency);

            if ($dailyAverage->isZero()) {
                continue;
            }

            $threshold = $dailyAverage->multiply(3);
            $balance = $this->balances->accountBalance($account->fresh());

            if ($balance->greaterThan($threshold) || $balance->isNegative()) {
                continue;
            }

            $alreadyNotified = $event->user->unreadNotifications()
                ->where('type', LowAccountBalance::class)
                ->where('data->account_id', $account->id)
                ->exists();

            if (! $alreadyNotified) {
                $event->user->notify(new LowAccountBalance($account, $balance));
            }
        }
    }

    private function trailingDailyExpenseAverage(int $userId, string $currency): Money
    {
        $total = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::Expense->value)
            ->where('accounts.currency', $currency)
            ->where('transactions.date', '>=', now()->subDays(30))
            ->sum('transactions.amount');

        return Money::of($total)->divide(30);
    }
}
