<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case DebitCard = 'debit_card';
    case CreditCard = 'credit_card';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::DebitCard => __('Debit card'),
            self::CreditCard => __('Credit card'),
            self::BankTransfer => __('Bank transfer'),
            self::Other => __('Other'),
        };
    }
}
