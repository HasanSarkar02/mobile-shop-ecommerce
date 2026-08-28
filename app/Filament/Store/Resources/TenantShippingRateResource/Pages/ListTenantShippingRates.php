<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\TenantShippingRateResource\Pages;

use App\Filament\Store\Resources\TenantShippingRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantShippingRates extends ListRecords
{
    protected static string $resource = TenantShippingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
