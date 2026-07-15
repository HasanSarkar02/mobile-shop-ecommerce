<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ShippingMethodResource\Pages;

use App\Filament\Store\Resources\ShippingMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingMethods extends ListRecords
{
    protected static string $resource = ShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}