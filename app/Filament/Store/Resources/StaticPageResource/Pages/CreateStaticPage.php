<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaticPageResource\Pages;

use App\Filament\Store\Resources\StaticPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaticPage extends CreateRecord
{
    protected static string $resource = StaticPageResource::class;
}
