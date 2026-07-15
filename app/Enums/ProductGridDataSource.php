<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductGridDataSource: string
{
    case Featured = 'featured';
    case Latest = 'latest';
    case BestSelling = 'best_selling';
    case FlashSale = 'flash_sale';
    case Campaign = 'campaign';
    case Category = 'category';
    case Collection = 'collection';
    case Tag = 'tag';

    public function label(): string
    {
        return match ($this) {
            self::Featured => 'Featured Products',
            self::Latest => 'Latest Arrivals',
            self::BestSelling => 'Best Selling (requires Orders)',
            self::FlashSale => 'Flash Sale (requires Pricing engine)',
            self::Campaign => 'Campaign Products',
            self::Category => 'Specific Category',
            self::Collection => 'Specific Collection',
            self::Tag => 'Specific Tag',
        };
    }
}