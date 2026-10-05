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
        return __('enums.payment_method.'.$this->value);
    }
}
