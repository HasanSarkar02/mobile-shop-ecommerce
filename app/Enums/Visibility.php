<?php

declare(strict_types=1);

namespace App\Enums;

enum Visibility: string
{
    case All = 'all';
    case Desktop = 'desktop';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Desktop & Mobile',
            self::Desktop => 'Desktop Only',
            self::Mobile => 'Mobile Only',
        };
    }
}
