<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaffResource\Pages;

use App\Filament\Store\Resources\StaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
