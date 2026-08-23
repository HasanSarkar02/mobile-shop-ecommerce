<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OutletResource\Pages;

use App\Filament\Store\Resources\OutletResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutlets extends ListRecords
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
