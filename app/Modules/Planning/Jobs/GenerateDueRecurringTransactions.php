<?php

namespace App\Modules\Planning\Jobs;

use App\Modules\Planning\Services\RecurringTransactionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly. Unique, so a queue backlog never runs two batches side by side; each template is also locked and
 * re-checked while it is generated (RecurringTransactionService).
 */
class GenerateDueRecurringTransactions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Seconds the uniqueness lock lasts if a run dies without releasing it. */
    public int $uniqueFor = 3600;

    public function handle(RecurringTransactionService $service): void
    {
        $service->generateDue();
    }
}
