<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CouponRedemptionResource\Pages;

use App\Filament\Store\Resources\CouponRedemptionResource;
use Filament\Resources\Pages\ListRecords;

class ListCouponRedemptions extends ListRecords
{
    protected static string $resource = CouponRedemptionResource::class;
}