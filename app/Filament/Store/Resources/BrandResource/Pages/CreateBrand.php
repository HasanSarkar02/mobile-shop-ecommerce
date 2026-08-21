<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\BrandResource\Pages;

use App\Filament\Store\Resources\BrandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;
}
