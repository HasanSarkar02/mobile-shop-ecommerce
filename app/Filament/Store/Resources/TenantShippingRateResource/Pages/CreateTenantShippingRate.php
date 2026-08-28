<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\TenantShippingRateResource\Pages;

use App\Filament\Store\Resources\TenantShippingRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantShippingRate extends CreateRecord
{
    protected static string $resource = TenantShippingRateResource::class;
}
