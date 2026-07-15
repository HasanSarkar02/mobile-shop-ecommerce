<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CollectionResource\Pages;

use App\Filament\Store\Resources\CollectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}