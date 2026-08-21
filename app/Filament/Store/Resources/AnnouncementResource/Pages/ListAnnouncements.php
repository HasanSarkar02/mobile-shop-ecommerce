<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AnnouncementResource\Pages;

use App\Filament\Store\Resources\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
