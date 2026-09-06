<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\SubdomainTombstoneResource\Pages;

use App\Filament\Platform\Resources\SubdomainTombstoneResource;
use Filament\Resources\Pages\ListRecords;

class ListSubdomainTombstones extends ListRecords
{
    protected static string $resource = SubdomainTombstoneResource::class;
}
