<?php

declare(strict_types=1);

namespace App\Enums;

enum HomepageSectionType: string
{
    case BannerCarousel = 'banner_carousel';
    case ProductGrid = 'product_grid';
    case CategoryGrid = 'category_grid';
    case CustomHtml = 'custom_html';

    public function label(): string
    {
        return match ($this) {
            self::BannerCarousel => 'Banner Carousel',
            self::ProductGrid => 'Product Grid',
            self::CategoryGrid => 'Category Grid',
            self::CustomHtml => 'Custom HTML',
        };
    }
}