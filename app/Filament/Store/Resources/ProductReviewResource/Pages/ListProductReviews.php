<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductReviewResource\Pages;

use App\Filament\Store\Resources\ProductReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListProductReviews extends ListRecords
{
    protected static string $resource = ProductReviewResource::class;
}
