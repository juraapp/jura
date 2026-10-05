<?php

namespace App\Jobs;

use App\Services\RecurringTransactionService;
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
