<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CollectionResource\Pages;

use App\Filament\Store\Resources\CollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}