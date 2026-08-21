<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\SerialNumberResource\Pages;

use App\Filament\Store\Resources\SerialNumberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSerialNumber extends CreateRecord
{
    protected static string $resource = SerialNumberResource::class;
}
