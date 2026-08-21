<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CourierConnectionResource\Pages;

use App\Filament\Store\Resources\CourierConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourierConnections extends ListRecords
{
    protected static string $resource = CourierConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
