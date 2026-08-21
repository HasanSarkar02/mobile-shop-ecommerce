<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\InventoryType;
use App\Enums\StockAdjustmentReason;
use App\Filament\Store\Resources\StockItemResource\Pages;
use App\Models\StockItem;
use App\Services\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('location.name'),
                TextColumn::make('quantity'),
                TextColumn::make('reserved_quantity')->label('Reserved'),
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (StockItem $record): int => $record->availableQuantity()),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (StockItem $record): string => app(InventoryService::class)->stockStatus($record->variant, $record->location)->label())
                    ->badge(),
            ])
            ->recordActions([
                Action::make('restock')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        TextInput::make('quantity')->numeric()->required()->minValue(1),
                        Textarea::make('comment')->rows(2),
                    ])
                    ->action(function (StockItem $record, array $data): void {
                        app(InventoryService::class)->restock($record->variant, (int) $data['quantity'], $record->location, $data['comment'] ?? null);
                    })
                    ->visible(fn (StockItem $record): bool => $record->variant->inventory_type !== InventoryType::Serialized),
                Action::make('adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        TextInput::make('quantity_change')->numeric()->required()->helperText('Use a negative number to decrease stock.'),
                        Select::make('reason')
                            ->options(collect(StockAdjustmentReason::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                            ->required(),
                        Textarea::make('comment')->rows(2),
                    ])
                    ->action(function (StockItem $record, array $data): void {
                        app(InventoryService::class)->adjust(
                            $record->variant,
                            (int) $data['quantity_change'],
                            StockAdjustmentReason::from($data['reason']),
                            $record->location,
                            $data['comment'] ?? null,
                        );
                    })
                    ->visible(fn (StockItem $record): bool => $record->variant->inventory_type !== InventoryType::Serialized),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockItems::route('/')];
    }
}
