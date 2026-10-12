<?php

namespace App\Modules\Ledger;

use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Listeners\InvalidateBalanceCache;
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
    }
}
