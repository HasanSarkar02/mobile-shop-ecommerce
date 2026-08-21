<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\PaymentMethodResource\Pages;

use App\Filament\Store\Resources\PaymentMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethod extends EditRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
