<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CouponResource\Pages;

use App\Filament\Store\Resources\CouponResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
