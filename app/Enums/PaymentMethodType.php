<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodType: string
{
    case Cod = 'cod';
    case ManualMfs = 'manual_mfs';
    case BankTransfer = 'bank_transfer';
    case OnlineGateway = 'online_gateway';
    case Aggregator = 'aggregator';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on Delivery',
            self::ManualMfs => 'Manual MFS (bKash / Nagad / Rocket)',
            self::BankTransfer => 'Bank Transfer',
            self::OnlineGateway => 'Online Gateway',
            self::Aggregator => 'Payment Aggregator (bKash/Nagad/Card) — deprecated',
        };
    }

    public function isManual(): bool
    {
        return $this === self::ManualMfs || $this === self::BankTransfer;
    }

    public function isCod(): bool
    {
        return $this === self::Cod;
    }

    public function isOnline(): bool
    {
        return $this === self::OnlineGateway || $this === self::Aggregator;
    }

    public function requiresVerification(): bool
    {
        return $this->isManual();
    }
}
