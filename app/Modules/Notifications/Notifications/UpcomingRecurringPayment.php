<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Planning\Models\RecurringTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UpcomingRecurringPayment extends Notification
{
    use Queueable;

    public function __construct(public readonly RecurringTransaction $recurring) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->recurring->description ?: $this->recurring->category->name;
        $amount = $notifiable->formatter()->money($this->recurring->amount, $this->recurring->account->currency);

        return [
            'type' => 'upcoming_payment',
            'severity' => 'warning',
            'message' => __('notifications.upcoming_payment', [
                'label' => $label,
                'amount' => $amount,
                'date' => $notifiable->formatter()->date($this->recurring->next_due_date, false),
            ]),
            'recurring_transaction_id' => $this->recurring->id,
            'url' => route('recurring-transactions.index'),
        ];
    }
}
