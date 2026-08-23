<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OutletResource\Pages;

use App\Filament\Store\Resources\OutletResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOutlet extends CreateRecord
{
    protected static string $resource = OutletResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
