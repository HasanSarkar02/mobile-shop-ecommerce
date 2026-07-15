<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\OrderResource\Pages;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable(),
                TextColumn::make('customerDisplayName')->label('Customer')->state(fn (Order $record): string => $record->customerDisplayName()),
                TextColumn::make('status')->badge(),
                TextColumn::make('order_source')->badge(),
                TextColumn::make('grand_total')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
                TextColumn::make('placed_at')->dateTime(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}