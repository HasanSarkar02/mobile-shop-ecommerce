<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\CourierProviderResource\Pages;

use App\Filament\Platform\Resources\CourierProviderResource;
use Filament\Resources\Pages\EditRecord;

class EditCourierProvider extends EditRecord
{
    protected static string $resource = CourierProviderResource::class;
}
