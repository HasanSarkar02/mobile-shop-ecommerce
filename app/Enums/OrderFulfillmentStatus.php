<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderFulfillmentStatus: string
{
    case Pending = 'pending';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Packed => 'Packed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }
}
