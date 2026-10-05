<?php

namespace App\Notifications;

use App\Models\RecurringTransaction;
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
        $amount = $this->recurring->amount->format($this->recurring->account->currency, $notifiable->locale);

        return [
            'type' => 'upcoming_payment',
            'severity' => 'warning',
            'message_key' => 'You have an upcoming payment: :label (:amount) on :date.',
            'message_params' => [
                'label' => $label,
                'amount' => $amount,
                'date' => $this->recurring->next_due_date->translatedFormat('d M'),
            ],
            'recurring_transaction_id' => $this->recurring->id,
            'url' => route('recurring-transactions.index'),
        ];
    }
}
