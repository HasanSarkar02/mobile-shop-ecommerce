<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function publish(Product $product): Product
    {
        if ($product->variants()->count() === 0) {
            throw ValidationException::withMessages([
                'status' => 'A product must have at least one variant before it can be published.',
            ]);
        }

        $product->update(['status' => ProductStatus::Published, 'published_at' => now()]);

        return $product;
    }

    public function archive(Product $product): Product
    {
        $product->update(['status' => ProductStatus::Archived]);

        return $product;
    }
}