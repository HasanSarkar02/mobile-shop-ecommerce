<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AnnouncementResource\Pages;

use App\Filament\Store\Resources\AnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
