<?php

use App\Modules\Ledger\LedgerServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    LedgerServiceProvider::class,
];
