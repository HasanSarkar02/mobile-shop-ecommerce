<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\PaymentMethodResource\Pages;

use App\Filament\Store\Resources\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;
}