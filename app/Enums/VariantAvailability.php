<?php

declare(strict_types=1);

namespace App\Enums;

enum VariantAvailability: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::OutOfStock => 'Out of Stock',
            self::Discontinued => 'Discontinued',
        };
    }
}
