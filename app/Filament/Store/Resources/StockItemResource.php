<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\InventoryType;
use App\Enums\StockAdjustmentReason;
use App\Filament\Store\Resources\StockItemResource\Pages;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Services\Inventory\StockValuationService;
use App\Services\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stock';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant' => fn ($q) => $q->withTrashed(), 'location']))
            ->columns([
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->state(function (StockItem $record): string {
                        if (! $record->variant) {
                            return 'Orphan/Deleted';
                        }

                        return $record->variant->trashed()
                            ? $record->variant->sku.' — deleted'
                            : $record->variant->sku;
                    })
                    ->color(fn (StockItem $record): ?string => ! $record->variant || $record->variant->trashed() ? 'danger' : null),
                TextColumn::make('location.name'),
                TextColumn::make('quantity'),
                TextColumn::make('reserved_quantity')->label('Reserved'),
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (StockItem $record): int => $record->availableQuantity()),
                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->state(function (StockItem $record): string {
                        $variant = $record->variant;
                        $cost = $variant instanceof ProductVariant ? $variant->cost_price : null;

                        return $cost !== null ? money((int) $cost) : '—';
                    })
                    ->color(function (StockItem $record): ?string {
                        $variant = $record->variant;

                        return ($variant instanceof ProductVariant ? $variant->cost_price : null) === null ? 'gray' : null;
                    }),
                TextColumn::make('total_value')
                    ->label('Total Value')
                    ->state(function (StockItem $record): string {
                        $service = app(StockValuationService::class);
                        $value = $service->valueOnHand($record);

                        return $value !== null ? money($value) : '—';
                    })
                    ->color(fn (StockItem $record): string => app(StockValuationService::class)->valueOnHand($record) === null ? 'gray' : 'success')
                    ->summarize(Summarizer::make()->label('Page Total')->using(function (\Illuminate\Database\Query\Builder $query): string {
                        $total = (clone $query)
                            ->join('product_variants', 'product_variants.id', '=', 'stock_items.product_variant_id')
                            ->whereNotNull('product_variants.cost_price')
                            ->selectRaw('COALESCE(SUM(stock_items.quantity * product_variants.cost_price), 0) as total')
                            ->value('total');

                        return money((int) round((float) ($total ?? 0)));
                    })),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(function (StockItem $record): string {
                        if (! $record->variant) {
                            return 'Orphan';
                        }

                        if ($record->variant->trashed()) {
                            return 'Variant deleted';
                        }

                        return app(InventoryService::class)->stockStatus($record->variant, $record->location)->label();
                    })
                    ->badge()
                    ->color(fn (StockItem $record): string => ! $record->variant || $record->variant->trashed() ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('restock')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        // Decimal step (Phase C-1): measured goods restock in
                        // fractions — the service normalizes via bcmath.
                        TextInput::make('quantity')->numeric()->step('0.001')->required()->minValue('0.001'),
                        Textarea::make('comment')->rows(2),
                    ])
                    ->action(function (StockItem $record, array $data): void {
                        app(InventoryService::class)->restock($record->variant, (string) $data['quantity'], $record->location, $data['comment'] ?? null);
                    })
                    ->visible(fn (StockItem $record): bool => $record->variant && $record->variant->inventory_type !== InventoryType::Serialized),
                Action::make('adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        TextInput::make('quantity_change')->numeric()->step('0.001')->required()->helperText('Use a negative number to decrease stock.'),
                        Select::make('reason')
                            ->options(collect(StockAdjustmentReason::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                            ->required(),
                        Textarea::make('comment')->rows(2),
                    ])
                    ->action(function (StockItem $record, array $data): void {
                        app(InventoryService::class)->adjust(
                            $record->variant,
                            (string) $data['quantity_change'],
                            StockAdjustmentReason::from($data['reason']),
                            $record->location,
                            $data['comment'] ?? null,
                        );
                    })
                    ->visible(fn (StockItem $record): bool => $record->variant && $record->variant->inventory_type !== InventoryType::Serialized),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockItems::route('/')];
    }
}
