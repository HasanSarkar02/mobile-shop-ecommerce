<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\HomepageSectionResource\Pages;

use App\Filament\Store\Resources\HomepageSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomepageSections extends ListRecords
{
    protected static string $resource = HomepageSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}