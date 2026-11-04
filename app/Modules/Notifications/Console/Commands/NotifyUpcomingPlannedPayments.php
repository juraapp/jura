<?php

namespace App\Modules\Notifications\Console\Commands;

use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Notifications\Notifications\UpcomingPlannedPayment;
use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-upcoming-planned-payments')]
#[Description('Remind about open planned expenses due within the next 3 days')]
class NotifyUpcomingPlannedPayments extends Command
{
    public function handle(): void
    {
        $events = PlannedEvent::withoutGlobalScope('user')
            ->with(['user', 'account' => fn ($query) => $query->withoutGlobalScope('user')])
            ->open()
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('date', [now()->subDay()->toDateString(), now()->addDays(4)->toDateString()])
            ->get()
            ->filter(function (PlannedEvent $event) {
                if ($event->user === null || ($event->account_id !== null && ($event->account === null || ! $event->account->is_active))) {
                    return false;
                }

                $today = $event->user->today();

                return $event->date->betweenIncluded($today, $today->addDays(3));
            });

        foreach ($events as $event) {
            $alreadyNotified = $event->user->unreadNotifications()
                ->where('type', UpcomingPlannedPayment::class)
                ->where('data->planned_event_id', $event->id)
                ->exists();

            if (! $alreadyNotified) {
                $event->user->notify(new UpcomingPlannedPayment($event));
            }
        }

        $this->info("Reviewed {$events->count()} upcoming planned payments.");
    }
}
