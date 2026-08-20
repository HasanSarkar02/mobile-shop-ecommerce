<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PlatformAdminResource\Pages;

use App\Filament\Platform\Resources\PlatformAdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAdmins extends ListRecords
{
    protected static string $resource = PlatformAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
