<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Restock = 'restock';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Reservation = 'reservation';
    case Release = 'release';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial Stock',
            self::Restock => 'Restock',
            self::Sale => 'Sale',
            self::Return => 'Return',
            self::Adjustment => 'Adjustment',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::Reservation => 'Reservation',
            self::Release => 'Release',
        };
    }
}
