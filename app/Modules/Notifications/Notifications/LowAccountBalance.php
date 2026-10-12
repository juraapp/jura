<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Ledger\Models\Account;
use App\Modules\Shared\Support\Money;
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
                'amount' => $notifiable->formatter()->money($this->balance, $this->account->currency),
            ]),
            'account_id' => $this->account->id,
            'url' => route('accounts.index'),
        ];
    }
}
