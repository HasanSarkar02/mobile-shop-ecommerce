<?php

declare(strict_types=1);

namespace App\Enums;

enum ShippingMethodType: string
{
    case FlatRate = 'flat_rate';
    case Free = 'free';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::FlatRate => 'Flat Rate',
            self::Free => 'Free Shipping',
            self::Pickup => 'Store Pickup',
        };
    }
}
