<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Off',
            self::FixedAmount => 'Fixed Amount Off',
            self::FreeShipping => 'Free Shipping',
        };
    }
}
