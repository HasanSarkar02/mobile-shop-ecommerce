<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CustomerResource\Pages;

use App\Filament\Store\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
