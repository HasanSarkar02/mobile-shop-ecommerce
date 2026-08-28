<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\TenantShippingRateResource\Pages;

use App\Filament\Store\Resources\TenantShippingRateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTenantShippingRate extends EditRecord
{
    protected static string $resource = TenantShippingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
