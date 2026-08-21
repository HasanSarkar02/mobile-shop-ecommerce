<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Bundle = 'bundle';
    case Digital = 'digital';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simple Product',
            self::Bundle => 'Bundle / Kit',
            self::Digital => 'Digital Product',
            self::Service => 'Service',
        };
    }
}
