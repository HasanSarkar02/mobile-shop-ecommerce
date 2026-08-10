<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\CouponRedemptionResource\Pages;
use App\Models\CouponRedemption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponRedemptionResource extends Resource
{
    protected static ?string $model = CouponRedemption::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Coupon Redemptions';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('redeemed_at')->dateTime(),
                TextColumn::make('coupon.code')->placeholder('Automatic'),
                TextColumn::make('coupon.name'),
                TextColumn::make('order.order_number'),
                TextColumn::make('customer.name')->placeholder('Guest'),
                TextColumn::make('discount_amount')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
            ])
            ->defaultSort('redeemed_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCouponRedemptions::route('/')];
    }
}