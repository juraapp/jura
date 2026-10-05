<?php

namespace App\Notifications;

use App\Models\Account;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowAccountBalance extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Account $account,
        public readonly Money $balance,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_balance',
            'severity' => 'warning',
            'message' => __('notifications.low_balance', [
                'account' => $this->account->name,
                'amount' => $this->balance->format($this->account->currency, $notifiable->locale),
            ]),
            'account_id' => $this->account->id,
            'url' => route('accounts.index'),
        ];
    }
}
