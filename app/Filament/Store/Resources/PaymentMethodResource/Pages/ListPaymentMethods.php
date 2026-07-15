<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\PaymentMethodResource\Pages;

use App\Filament\Store\Resources\PaymentMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}