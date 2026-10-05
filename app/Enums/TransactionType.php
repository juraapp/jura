<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

    public function label(): string
    {
        return match ($this) {
            self::Income => __('Income'),
            self::Expense => __('Expense'),
            self::TransferOut, self::TransferIn => __('Transfer'),
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::TransferOut || $this === self::TransferIn;
    }
}
