<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSource: string
{
    case Website = 'website';
    case Admin = 'admin';
    case Phone = 'phone';
    case Api = 'api';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Admin => 'Admin (manual)',
            self::Phone => 'Phone Order',
            self::Api => 'API',
            self::Pos => 'POS',
        };
    }
}
