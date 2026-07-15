<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\MenuResource\Pages;

use App\Filament\Store\Resources\MenuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}