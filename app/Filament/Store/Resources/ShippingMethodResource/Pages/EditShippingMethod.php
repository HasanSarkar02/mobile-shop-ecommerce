<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ShippingMethodResource\Pages;

use App\Filament\Store\Resources\ShippingMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingMethod extends EditRecord
{
    protected static string $resource = ShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
