<?php

namespace App\Enums;

enum AccountType: string
{
    case Bank = 'bank';
    case Wallet = 'wallet';
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case Savings = 'savings';

    public function label(): string
    {
        return match ($this) {
            self::Bank => __('Bank account'),
            self::Wallet => __('Digital wallet'),
            self::Cash => __('Cash'),
            self::CreditCard => __('Credit card'),
            self::Savings => __('Savings account'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Bank, self::Savings => 'banknotes',
            self::Wallet => 'wallet',
            self::Cash => 'wallet',
            self::CreditCard => 'wallet',
        };
    }
}
