<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\DomainResource\Pages;

use App\Filament\Platform\Resources\DomainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDomains extends ListRecords
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Domain'),
        ];
    }
}
