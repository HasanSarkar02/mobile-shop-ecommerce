<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaticPageResource\Pages;

use App\Filament\Store\Resources\StaticPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaticPages extends ListRecords
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}