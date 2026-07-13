<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\LocationResource\Pages;

use App\Filament\Store\Resources\LocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}