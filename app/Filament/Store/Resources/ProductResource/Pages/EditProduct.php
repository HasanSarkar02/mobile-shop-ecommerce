<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\Pages;

use App\Enums\InventoryType;
use App\Enums\StockAdjustmentReason;
use App\Filament\Store\Resources\ProductResource;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->manageStockAction(),
            DeleteAction::make(),
        ];
    }

    private function manageStockAction(): Action
    {
        return Action::make('manage_stock')
            ->label('Manage Stock')
            ->icon('heroicon-o-archive-box')
            ->color('success')
            ->modalHeading(fn (?Product $record) => $record ? 'Manage Stock — '.($record->name ?? $record->id) : 'Manage Stock')
            ->modalWidth('5xl')
            ->modalDescription('Update inventory for all variants at once. Enter a quantity to add per variant — leave blank to skip.')
            ->form(function (?Product $record): array {
                if (! $record) {
                    return [];
                }

                $inventory = app(InventoryService::class);
                $defaultLocation = $inventory->defaultLocation();

                $variants = $record->variants()
                    ->with(['stockItems.location', 'media'])
                    ->orderBy('sku')
                    ->get();

                if ($variants->isEmpty()) {
                    return [
                        Placeholder::make('empty')
                            ->content('No variants for this product. Create variants first.')
                            ->columnSpanFull(),
                    ];
                }

                // Preload stock map for default location — direct query is tenant-safe and not dependent on eager collection
                $stockItems = StockItem::query()
                    ->whereIn('product_variant_id', $variants->pluck('id'))
                    ->where('location_id', $defaultLocation->id)
                    ->get()
                    ->keyBy('product_variant_id');

                $defaultStocks = $variants->map(function (ProductVariant $variant) use ($stockItems, $record) {
                    $item = $stockItems->get($variant->id);
                    $available = $item instanceof StockItem ? $item->availableQuantityDecimal() : '0.000';
                    $isSerialized = $variant->inventory_type === InventoryType::Serialized;
                    $thumbUrl = $variant->getFirstMediaUrl('images', 'thumb') ?: $record->getFirstMediaUrl('images', 'thumb') ?: null;

                    return [
                        'variant_id' => $variant->id,
                        'sku' => $variant->sku,
                        'variant_label' => $variant->sku.($isSerialized ? ' · Serialized' : ''),
                        'thumb_url' => $thumbUrl,
                        'current_stock' => $available,
                        'is_serialized' => $isSerialized,
                        'quantity_change' => null,
                    ];
                })->toArray();

                return [
                    Select::make('location_id')
                        ->label('Location')
                        ->options(fn () => Location::query()->where('tenant_id', tenant()?->id)->pluck('name', 'id')->all())
                        ->default($defaultLocation->id)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                            $locationId = $state ?: app(InventoryService::class)->defaultLocation()->id;
                            $location = Location::query()->find($locationId);
                            if (! $location) {
                                return;
                            }
                            $stocks = $get('stocks') ?? [];
                            if (! is_array($stocks) || $stocks === []) {
                                return;
                            }
                            $variantIds = collect($stocks)->pluck('variant_id')->filter()->values();
                            if ($variantIds->isEmpty()) {
                                return;
                            }
                            $items = StockItem::query()
                                ->whereIn('product_variant_id', $variantIds)
                                ->where('location_id', $location->id)
                                ->get()
                                ->keyBy('product_variant_id');
                            $newStocks = collect($stocks)->map(function ($row) use ($items) {
                                $vid = $row['variant_id'] ?? null;
                                $item = $vid ? $items->get($vid) : null;
                                $row['current_stock'] = $item instanceof StockItem ? $item->availableQuantityDecimal() : '0.000';

                                return $row;
                            })->all();
                            $set('stocks', $newStocks);
                        })
                        ->helperText('Stock will be updated at this location. Changing location refreshes Current available.')
                        ->columnSpanFull(),

                    Select::make('mode')
                        ->label('Operation')
                        ->options([
                            'restock' => 'Restock (add stock)',
                            'adjust' => 'Adjust (increase/decrease with reason)',
                        ])
                        ->default('restock')
                        ->live()
                        ->required()
                        ->helperText('Restock adds to quantity; Adjust can add or subtract.'),

                    Select::make('reason')
                        ->label('Adjustment Reason')
                        ->options(collect(StockAdjustmentReason::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->visible(fn ($get) => $get('mode') === 'adjust')
                        ->required(fn ($get) => $get('mode') === 'adjust')
                        ->helperText('Required for adjustments.'),

                    Textarea::make('comment')
                        ->label('Comment (optional)')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder('Reason for this stock update...'),

                    Repeater::make('stocks')
                        ->label('Variants')
                        ->schema([
                            Hidden::make('variant_id'),
                            Hidden::make('is_serialized'),
                            Hidden::make('thumb_url'),
                            Placeholder::make('thumb')
                                ->label('Image')
                                ->content(function (Get $get): HtmlString {
                                    $url = $get('thumb_url');
                                    if (! $url) {
                                        return new HtmlString('<span class="text-xs text-gray-400">—</span>');
                                    }

                                    return new HtmlString('<img src="'.e($url).'" class="w-10 h-10 rounded object-cover border border-gray-200 dark:border-gray-700" loading="lazy" alt="" />');
                                }),
                            Placeholder::make('variant_label')
                                ->label('Variant'),
                            Placeholder::make('current_stock')
                                ->label('Current available'),
                            TextInput::make('quantity_change')
                                ->label('Qty to add')
                                ->numeric()
                                ->step('0.001')
                                ->placeholder('0.000')
                                ->helperText(fn ($get) => $get('is_serialized') ? 'Serialized — manage via Serial Numbers' : 'Leave blank to skip')
                                ->disabled(fn ($get) => (bool) $get('is_serialized'))
                                ->dehydrated(fn ($get) => ! (bool) $get('is_serialized')),
                        ])
                        ->default($defaultStocks)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsible(false)
                        ->columns(5)
                        ->columnSpanFull()
                        ->helperText('Tab through fields to quickly enter quantities for 10 variants.'),
                ];
            })
            ->action(function (array $data, Product $record): void {
                $locationId = $data['location_id'] ?? null;
                $location = $locationId ? Location::query()->find($locationId) : app(InventoryService::class)->defaultLocation();
                $mode = $data['mode'] ?? 'restock';
                $comment = $data['comment'] ?? null;
                $reason = null;

                if ($mode === 'adjust' && ! empty($data['reason'])) {
                    $reason = StockAdjustmentReason::from($data['reason']);
                }

                $inventory = app(InventoryService::class);
                $stocks = $data['stocks'] ?? [];

                $processed = 0;
                $skippedSerialized = 0;

                DB::transaction(function () use ($stocks, $location, $mode, $reason, $comment, $inventory, &$processed, &$skippedSerialized): void {
                    foreach ($stocks as $row) {
                        $variantId = $row['variant_id'] ?? null;
                        $rawQty = $row['quantity_change'] ?? null;

                        if ($variantId === null || $rawQty === null || $rawQty === '') {
                            continue;
                        }

                        $qty = trim((string) $rawQty);
                        if ($qty === '' || ! is_numeric($qty) || (float) $qty == 0.0) {
                            continue;
                        }

                        $variant = ProductVariant::query()->find($variantId);
                        if (! $variant) {
                            continue;
                        }

                        if ($variant->inventory_type === InventoryType::Serialized) {
                            $skippedSerialized++;

                            continue;
                        }

                        if ($mode === 'restock') {
                            // Restock expects positive quantity; clamp negative to adjust path
                            if (str_starts_with($qty, '-')) {
                                // Treat negative restock as adjust with reason if provided, else skip
                                continue;
                            }

                            $inventory->restock($variant, $qty, $location, $comment);
                        } else {
                            $inventory->adjust($variant, $qty, $reason, $location, $comment);
                        }

                        $processed++;
                    }
                });

                if ($processed === 0 && $skippedSerialized === 0) {
                    Notification::make()
                        ->title('No stock changes made')
                        ->body('Leave a quantity for at least one variant.')
                        ->warning()
                        ->send();

                    return;
                }

                $body = "Updated {$processed} variant(s) at {$location->name}.";
                if ($skippedSerialized > 0) {
                    $body .= " Skipped {$skippedSerialized} serialized variant(s).";
                }

                Notification::make()
                    ->title('Stock updated')
                    ->body($body)
                    ->success()
                    ->send();
            });
    }
}
