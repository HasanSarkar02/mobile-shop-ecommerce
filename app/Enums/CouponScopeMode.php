<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponScopeMode: string
{
    case Include = 'include';
    case Exclude = 'exclude';

    public function label(): string
    {
        return match ($this) {
            self::Include => 'Include only these',
            self::Exclude => 'Exclude these',
        };
    }
}
