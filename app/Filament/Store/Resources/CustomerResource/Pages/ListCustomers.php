<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CustomerResource\Pages;

use App\Filament\Store\Resources\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}