<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaticPageResource\Pages;

use App\Filament\Store\Resources\StaticPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
