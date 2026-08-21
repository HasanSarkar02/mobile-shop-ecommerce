<?php

declare(strict_types=1);

namespace App\Enums;

enum BannerPlacement: string
{
    case Hero = 'hero';
    case Sidebar = 'sidebar';
    case Popup = 'popup';
    case Promo = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::Sidebar => 'Sidebar',
            self::Popup => 'Popup',
            self::Promo => 'Promo',
        };
    }
}
