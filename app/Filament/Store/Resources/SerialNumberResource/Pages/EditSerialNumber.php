<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\SerialNumberResource\Pages;

use App\Filament\Store\Resources\SerialNumberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSerialNumber extends EditRecord
{
    protected static string $resource = SerialNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}