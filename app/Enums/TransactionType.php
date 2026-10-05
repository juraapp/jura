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
            self::Income => __('enums.transaction_type.income'),
            self::Expense => __('enums.transaction_type.expense'),
            self::TransferOut, self::TransferIn => __('enums.transaction_type.transfer'),
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::TransferOut || $this === self::TransferIn;
    }
}
