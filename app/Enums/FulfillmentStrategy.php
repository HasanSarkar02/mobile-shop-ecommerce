<?php

declare(strict_types=1);

namespace App\Enums;

enum FulfillmentStrategy: string
{
    case Stock = 'stock';
    case Preorder = 'preorder';
    case Dropship = 'dropship';

    public function label(): string
    {
        return match ($this) {
            self::Stock => 'From Stock',
            self::Preorder => 'Pre-Order',
            self::Dropship => 'Dropship',
        };
    }
}
