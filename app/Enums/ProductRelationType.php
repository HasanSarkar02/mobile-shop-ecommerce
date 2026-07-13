<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductRelationType: string
{
    case Related = 'related';
    case CrossSell = 'cross_sell';
    case Upsell = 'upsell';
    case FrequentlyBoughtTogether = 'frequently_bought_together';
    case Compatible = 'compatible';

    public function label(): string
    {
        return match ($this) {
            self::Related => 'Related Product',
            self::CrossSell => 'Cross-sell',
            self::Upsell => 'Upsell',
            self::FrequentlyBoughtTogether => 'Frequently Bought Together',
            self::Compatible => 'Compatible Accessory',
        };
    }
}