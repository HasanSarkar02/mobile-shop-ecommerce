<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryType: string
{
    case Tracked = 'tracked';
    case Serialized = 'serialized';
    case NotTracked = 'not_tracked';

    public function label(): string
    {
        return match ($this) {
            self::Tracked => 'Tracked (quantity)',
            self::Serialized => 'Serialized (IMEI/serial)',
            self::NotTracked => 'Not Tracked',
        };
    }
}