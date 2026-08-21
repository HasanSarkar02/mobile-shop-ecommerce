<?php

declare(strict_types=1);

namespace App\Filament\Store\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest('placed_at')->limit(5))
            ->columns([
                TextColumn::make('order_number'),
                TextColumn::make('customer')->label('Customer')->state(fn (Order $record) => $record->customerDisplayName()),
                TextColumn::make('status')->badge(),
                TextColumn::make('grand_total')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
                TextColumn::make('placed_at')->dateTime(),
            ])
            ->paginated(false);
    }
}
