<?php

declare(strict_types=1);

namespace App\Enums;

enum StockAdjustmentReason: string
{
    case Recount = 'recount';
    case Damaged = 'damaged';
    case TheftOrLoss = 'theft_or_loss';
    case Expired = 'expired';
    case ReturnedToSupplier = 'returned_to_supplier';
    case Correction = 'correction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Recount => 'Recount',
            self::Damaged => 'Damaged',
            self::TheftOrLoss => 'Theft / Loss',
            self::Expired => 'Expired',
            self::ReturnedToSupplier => 'Returned to Supplier',
            self::Correction => 'Correction',
            self::Other => 'Other',
        };
    }
}