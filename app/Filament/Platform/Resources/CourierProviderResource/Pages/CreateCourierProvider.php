<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\CourierProviderResource\Pages;

use App\Filament\Platform\Resources\CourierProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourierProvider extends CreateRecord
{
    protected static string $resource = CourierProviderResource::class;
}
