<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\SubscriptionChargeResource\Pages;

use App\Filament\Platform\Resources\SubscriptionChargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionCharges extends ListRecords
{
    protected static string $resource = SubscriptionChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Create Charge'),
        ];
    }
}
