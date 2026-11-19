<?php

namespace App\Modules\Ledger;

use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Events\AccountBudgetChanged;
use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Listeners\InvalidateBalanceCache;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Services\BalanceCalculator;
use App\Modules\Ledger\Services\EloquentLedgerQuery;
use App\Modules\Ledger\Services\TransactionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Balances::class, BalanceCalculator::class);
        $this->app->bind(LedgerQuery::class, EloquentLedgerQuery::class);
        $this->app->bind(RecordsTransactions::class, TransactionService::class);
    }

    public function boot(): void
    {
        Event::listen(TransactionsMutated::class, InvalidateBalanceCache::class);

        $forgetBalance = fn (Account $account) => $this->app->make(BalanceCalculator::class)->forgetAccount($account);
        Account::saved($forgetBalance);
        Account::deleted($forgetBalance);
        Account::restored($forgetBalance);

        Account::updated(function (Account $account) {
            $was = (bool) $account->getOriginal('on_budget') && (bool) $account->getOriginal('is_active');
            $is = $account->on_budget && $account->is_active;

            if ($was !== $is) {
                AccountBudgetChanged::dispatch($account->user_id, $account->id, $is);
            }
        });
    }
}
