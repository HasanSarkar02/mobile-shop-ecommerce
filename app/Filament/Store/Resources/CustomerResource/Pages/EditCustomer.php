<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CustomerResource\Pages;

use App\Filament\Store\Resources\CustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
