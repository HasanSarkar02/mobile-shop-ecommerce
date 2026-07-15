<?php

declare(strict_types=1);

namespace App\Enums;

enum LinkType: string
{
    case Product = 'product';
    case Category = 'category';
    case Brand = 'brand';
    case Collection = 'collection';
    case StaticPage = 'static_page';
    case External = 'external';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Category => 'Category',
            self::Brand => 'Brand',
            self::Collection => 'Collection',
            self::StaticPage => 'Static Page',
            self::External => 'External URL',
            self::None => 'No Link',
        };
    }
}
