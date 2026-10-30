<?php

namespace App\Modules\Notifications\Console\Commands;

use App\Modules\Notifications\Notifications\UpcomingRecurringPayment;
use App\Modules\Planning\Models\RecurringTransaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-upcoming-recurring-payments')]
#[Description('Notify recurring payments due within the next 3 days')]
class NotifyUpcomingRecurringPayments extends Command
{
    public function handle(): void
    {
        $recurrences = RecurringTransaction::with(['user', 'category', 'account'])
            ->whereHas('account')
            ->where('is_active', true)
            ->whereBetween('next_due_date', [now()->subDay()->toDateString(), now()->addDays(4)->toDateString()])
            ->get()
            ->filter(function (RecurringTransaction $recurring) {
                $today = $recurring->user->today();

                return $recurring->next_due_date->betweenIncluded($today, $today->addDays(3));
            });

        foreach ($recurrences as $recurring) {
            $alreadyNotified = $recurring->user->unreadNotifications()
                ->where('type', UpcomingRecurringPayment::class)
                ->where('data->recurring_transaction_id', $recurring->id)
                ->exists();

            if (! $alreadyNotified) {
                $recurring->user->notify(new UpcomingRecurringPayment($recurring));
            }
        }

        $this->info("Reviewed {$recurrences->count()} upcoming recurring payments.");
    }
}
