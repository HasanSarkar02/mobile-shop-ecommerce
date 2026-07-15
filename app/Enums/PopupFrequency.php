<?php

declare(strict_types=1);

namespace App\Enums;

enum PopupFrequency: string
{
    case OncePerSession = 'once_per_session';
    case OncePerDay = 'once_per_day';
    case Always = 'always';

    public function label(): string
    {
        return match ($this) {
            self::OncePerSession => 'Once per session',
            self::OncePerDay => 'Once per day',
            self::Always => 'Every page load',
        };
    }
}