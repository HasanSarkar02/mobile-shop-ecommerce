<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NotificationTemplateResource\Pages;

use App\Filament\Store\Resources\NotificationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationTemplates extends ListRecords
{
    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
