<?php

declare(strict_types=1);

namespace App\Filament\Store\Widgets;

use App\Models\StockItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?string $heading = 'Low Stock';

    public function table(Table $table): Table
    {
        return $table
            ->query(StockItem::query()->whereRaw('(quantity - reserved_quantity) <= COALESCE(low_stock_threshold, 5)')->limit(5))
            ->columns([
                TextColumn::make('variant.sku')->label('SKU'),
                TextColumn::make('quantity'),
                TextColumn::make('reserved_quantity')->label('Reserved'),
            ])
            ->paginated(false);
    }
}
