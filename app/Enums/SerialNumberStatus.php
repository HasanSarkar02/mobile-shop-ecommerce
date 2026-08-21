<?php

declare(strict_types=1);

namespace App\Enums;

enum SerialNumberStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
    case Reserved = 'reserved';
    case Defective = 'defective';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Sold => 'Sold',
            self::Reserved => 'Reserved',
            self::Defective => 'Defective',
            self::Returned => 'Returned',
        };
    }
}
