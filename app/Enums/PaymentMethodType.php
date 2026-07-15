<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodType: string
{
    case Cod = 'cod';
    case Aggregator = 'aggregator';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on Delivery',
            self::Aggregator => 'Payment Aggregator (bKash/Nagad/Card)',
            self::BankTransfer => 'Bank Transfer',
        };
    }
}