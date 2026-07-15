<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NotificationLogResource\Pages;

use App\Filament\Store\Resources\NotificationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationLogs extends ListRecords
{
    protected static string $resource = NotificationLogResource::class;
}