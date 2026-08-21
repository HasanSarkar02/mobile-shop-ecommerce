<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\RedirectResource\Pages;

use App\Filament\Store\Resources\RedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
