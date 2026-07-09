<?php

namespace App\Observers;

use App\Enums\ProductStatus;
use App\Events\ProductPublished;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        //
    }

    public function saved(Product $product): void
    {
        Cache::forget("tenant:{$product->tenant_id}:catalog:category:{$product->category_id}");

        if ($product->wasChanged('status') && $product->status === ProductStatus::Published) {
            ProductPublished::dispatch($product);
        }
    }

    public function deleted(Product $product): void
    {
        Cache::forget("tenant:{$product->tenant_id}:catalog:category:{$product->category_id}");
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }
}
