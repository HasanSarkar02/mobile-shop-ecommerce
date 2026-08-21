<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CouponResource\Pages;

use App\Filament\Store\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;
}
