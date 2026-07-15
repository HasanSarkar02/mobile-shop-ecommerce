<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AnnouncementResource\Pages;

use App\Filament\Store\Resources\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;
}