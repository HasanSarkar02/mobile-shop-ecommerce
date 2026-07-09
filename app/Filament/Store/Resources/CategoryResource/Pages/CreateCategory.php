<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CategoryResource\Pages;

use App\Filament\Store\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}