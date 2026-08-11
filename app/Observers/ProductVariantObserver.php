<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CartItem;
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
        $this->removeOrphanedCartItems($variant);
        $this->syncBasePrice($variant);
    }

    /**
     * Remove cart items left pointing at a variant that is no longer available.
     * Runs on soft delete too, so cart pages can never resolve a null variant.
     */
    private function removeOrphanedCartItems(ProductVariant $variant): void
    {
        CartItem::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $variant->tenant_id)
            ->where('product_variant_id', $variant->id)
            ->delete();
    }

    private function syncBasePrice(ProductVariant $variant): void
    {
        $activeVariants = $variant->product->variants()->where('is_active', true)->get();

        $minPrice = $activeVariants->min('price');

        $maxDiscount = $activeVariants
            ->map(fn (ProductVariant $v) => $v->discountPercentage())
            ->filter()
            ->max();

        $variant->product->update([
            'base_price' => $minPrice ?? 0,
            'max_discount_percentage' => $maxDiscount,
        ]);
    }
}