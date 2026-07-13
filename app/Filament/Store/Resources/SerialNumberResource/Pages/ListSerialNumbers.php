<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\SerialNumberResource\Pages;

use App\Filament\Store\Resources\SerialNumberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSerialNumbers extends ListRecords
{
    protected static string $resource = SerialNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}