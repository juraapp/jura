<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Notifications\UpcomingRecurringPayment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-upcoming-recurring-payments')]
#[Description('Notifies recurring payments due within the next 3 days')]
class NotifyUpcomingRecurringPayments extends Command
{
    public function handle(): void
    {
        $recurrences = RecurringTransaction::with(['user', 'category'])
            ->where('is_active', true)
            ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(3)->toDateString()])
            ->get();

        foreach ($recurrences as $recurring) {
            $alreadyNotified = $recurring->user->unreadNotifications()
                ->where('type', UpcomingRecurringPayment::class)
                ->where('data->recurring_transaction_id', $recurring->id)
                ->exists();

            if (! $alreadyNotified) {
                $recurring->user->notify(new UpcomingRecurringPayment($recurring));
            }
        }

        $this->info("Checked {$recurrences->count()} upcoming recurring transactions.");
    }
}
