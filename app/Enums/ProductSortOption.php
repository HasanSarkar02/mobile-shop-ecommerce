<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductSortOption: string
{
    case Featured = 'featured';
    case Newest = 'newest';
    case PriceLow = 'price_low';
    case PriceHigh = 'price_high';
    case BestSelling = 'best_selling';
    case MostViewed = 'most_viewed';
    case NameAsc = 'name_asc';

    public function label(): string
    {
        return match ($this) {
            self::Featured => 'Featured',
            self::Newest => 'Newest',
            self::PriceLow => 'Price: Low to High',
            self::PriceHigh => 'Price: High to Low',
            self::BestSelling => 'Best Selling',
            self::MostViewed => 'Most Viewed',
            self::NameAsc => 'Name: A-Z',
        };
    }
}