<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\BannerResource\Pages;

use App\Filament\Store\Resources\BannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;
}