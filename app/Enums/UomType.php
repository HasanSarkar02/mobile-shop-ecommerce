<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Physical kind of a unit of measure (Phase C-1). Drives whether stock is
 * tracked in whole units (discrete fast-path) or measured decimals.
 */
enum UomType: string
{
    case Weight = 'weight';
    case Volume = 'volume';
    case Length = 'length';
    case Discrete = 'discrete';

    public function label(): string
    {
        return match ($this) {
            self::Weight => 'Weight',
            self::Volume => 'Volume',
            self::Length => 'Length',
            self::Discrete => 'Discrete',
        };
    }
}
