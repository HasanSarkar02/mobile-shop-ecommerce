<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponEligibilityScope: string
{
    case All = 'all';
    case Specific = 'specific';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Products',
            self::Specific => 'Specific Products/Categories/Brands/Collections',
        };
    }
}