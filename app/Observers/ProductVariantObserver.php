<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockItem;

class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        $location = Location::query()
            ->where('tenant_id', $variant->tenant_id)
            ->where('is_default', true)
            ->first();

        if ($location) {
            StockItem::query()->firstOrCreate(
                ['product_variant_id' => $variant->id, 'location_id' => $location->id],
                ['tenant_id' => $variant->tenant_id, 'quantity' => 0, 'reserved_quantity' => 0],
            );
        }
    }

    public function saved(ProductVariant $variant): void
    {
        $this->syncBasePrice($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->syncBasePrice($variant);
    }

    private function syncBasePrice(ProductVariant $variant): void
    {
        $minPrice = $variant->product->variants()->where('is_active', true)->min('price');

        $variant->product->update(['base_price' => $minPrice ?? 0]);
    }
}