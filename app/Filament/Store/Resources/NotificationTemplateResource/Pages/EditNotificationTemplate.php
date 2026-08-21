<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NotificationTemplateResource\Pages;

use App\Filament\Store\Resources\NotificationTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNotificationTemplate extends EditRecord
{
    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
