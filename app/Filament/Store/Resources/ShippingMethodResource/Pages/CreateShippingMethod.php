<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ShippingMethodResource\Pages;

use App\Filament\Store\Resources\ShippingMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingMethod extends CreateRecord
{
    protected static string $resource = ShippingMethodResource::class;
}
