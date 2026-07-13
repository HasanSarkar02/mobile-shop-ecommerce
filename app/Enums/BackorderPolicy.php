<?php

declare(strict_types=1);

namespace App\Enums;

enum BackorderPolicy: string
{
    case Deny = 'deny';
    case Allow = 'allow';
    case Notify = 'notify';

    public function label(): string
    {
        return match ($this) {
            self::Deny => 'Deny (block when out of stock)',
            self::Allow => 'Allow (sell past zero silently)',
            self::Notify => 'Allow with notice (show ships-later message)',
        };
    }
}