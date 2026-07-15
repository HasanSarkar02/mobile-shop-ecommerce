<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\MenuResource\Pages;

use App\Filament\Store\Resources\MenuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;
}