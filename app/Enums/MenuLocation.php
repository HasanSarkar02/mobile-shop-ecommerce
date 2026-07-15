<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuLocation: string
{
    case Header = 'header';
    case Mobile = 'mobile';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Header',
            self::Mobile => 'Mobile (optional override)',
            self::Footer => 'Footer',
        };
    }
}