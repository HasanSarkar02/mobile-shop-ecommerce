<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\RedirectResource\Pages;

use App\Filament\Store\Resources\RedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRedirect extends CreateRecord
{
    protected static string $resource = RedirectResource::class;
}