<?php

namespace App\Modules\Planning\Jobs;

use App\Modules\Planning\Services\RecurringTransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDueRecurringTransactions implements ShouldQueue
{
    use Queueable;

    public function handle(RecurringTransactionService $service): void
    {
        $service->generateDue();
    }
}
