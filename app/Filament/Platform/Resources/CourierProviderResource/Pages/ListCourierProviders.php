<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\CourierProviderResource\Pages;

use App\Filament\Platform\Resources\CourierProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourierProviders extends ListRecords
{
    protected static string $resource = CourierProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
