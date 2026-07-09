<?php

namespace App\Filament\Store\Resources\Tags\Pages;

use App\Filament\Store\Resources\Tags\TagResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;
}
