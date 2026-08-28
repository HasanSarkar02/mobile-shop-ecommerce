<?php

declare(strict_types=1);

namespace App\Filament\Store\Imports;

use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Services\InventoryService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

class StockItemImporter extends Importer
{
    protected static ?string $model = StockItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->label('SKU')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('SKU-TSHIRT-M-RED'),
            ImportColumn::make('location')
                ->label('Location')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('Main Store'),
            ImportColumn::make('quantity')
                ->label('Quantity to add')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0.001'])
                ->example('25'),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $sku = trim((string) ($this->data['sku'] ?? ''));
        $locationName = trim((string) ($this->data['location'] ?? ''));
        $quantityRaw = $this->data['quantity'] ?? null;

        if ($sku === '' || $locationName === '' || $quantityRaw === null) {
            return null;
        }

        $variant = ProductVariant::query()
            ->where('sku', $sku)
            ->where('tenant_id', tenant()?->id)
            ->first();

        if (! $variant) {
            return null;
        }

        $location = Location::query()
            ->where('tenant_id', tenant()?->id)
            ->where('name', $locationName)
            ->first();

        if (! $location) {
            $location = app(InventoryService::class)->defaultLocation();
        }

        // Ensure stock item exists (quantity 0 if new) so restock has a row to update
        $stockItem = StockItem::query()->firstOrCreate(
            [
                'tenant_id' => $variant->tenant_id,
                'product_variant_id' => $variant->id,
                'location_id' => $location->id,
            ],
            [
                'quantity' => '0.000',
                'reserved_quantity' => '0.000',
            ],
        );

        // Stash resolved variant/location for saveRecord
        $this->data['_variant_id'] = $variant->id;
        $this->data['_location_id'] = $location->id;

        return $stockItem;
    }

    public function saveRecord(): void
    {
        $quantity = $this->data['quantity'] ?? null;
        $variantId = $this->data['_variant_id'] ?? null;
        $locationId = $this->data['_location_id'] ?? null;

        if ($quantity === null || $variantId === null || $locationId === null) {
            $this->record?->save();

            return;
        }

        $variant = ProductVariant::query()->find($variantId);
        $location = Location::query()->find($locationId);

        if (! $variant || ! $location) {
            $this->record?->save();

            return;
        }

        // Use service so movement is logged and bcmath is respected; skip serialized
        if ($variant->inventory_type->value === 'serialized') {
            return;
        }

        app(InventoryService::class)->restock($variant, (string) $quantity, $location, 'CSV import');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your stock import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
