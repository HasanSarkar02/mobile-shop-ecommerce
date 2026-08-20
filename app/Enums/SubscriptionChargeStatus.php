<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionChargeStatus: string
{
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }
}
