<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\LocationResource\Pages;

use App\Filament\Store\Resources\LocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;
}
