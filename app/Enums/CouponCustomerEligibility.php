<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponCustomerEligibility: string
{
    case All = 'all';
    case FirstOrderOnly = 'first_order_only';
    case SpecificCustomers = 'specific_customers';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Customers',
            self::FirstOrderOnly => 'First Order Only',
            self::SpecificCustomers => 'Specific Customers',
        };
    }
}