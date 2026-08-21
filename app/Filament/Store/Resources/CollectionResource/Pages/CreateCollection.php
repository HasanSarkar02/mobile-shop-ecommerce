<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CollectionResource\Pages;

use App\Filament\Store\Resources\CollectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;
}
