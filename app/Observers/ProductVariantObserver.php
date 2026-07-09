<?php

namespace App\Observers;

use App\Models\ProductVariant;

class ProductVariantObserver
{
    /**
     * Handle the ProductVariant "created" event.
     */
    public function created(ProductVariant $productVariant): void
    {
        //
    }

    /**
     * Handle the ProductVariant "updated" event.
     */
    public function updated(ProductVariant $productVariant): void
    {
        //
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

    /**
     * Handle the ProductVariant "restored" event.
     */
    public function restored(ProductVariant $productVariant): void
    {
        //
    }

    /**
     * Handle the ProductVariant "force deleted" event.
     */
    public function forceDeleted(ProductVariant $productVariant): void
    {
        //
    }
}
