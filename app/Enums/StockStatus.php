<?php

declare(strict_types=1);

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case Preorder = 'preorder';
    case Dropship = 'dropship';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::LowStock => 'Low Stock',
            self::OutOfStock => 'Out of Stock',
            self::Preorder => 'Pre-Order',
            self::Dropship => 'Dropship',
            self::Discontinued => 'Discontinued',
        };
    }
}
