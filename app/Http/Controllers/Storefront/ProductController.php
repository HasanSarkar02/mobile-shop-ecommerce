<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $product = Product::published()
            ->whereHas('translations', fn ($query) => $query->where('slug', $slug))
            ->with([
                'translations',
                'brand',
                'category',
                'variants.media',
                'media',
                'attributeValues.attributeDefinition',
                'attributeValues.attributeOption',
                'emiPlans' => fn ($query) => $query->where('active', true),
            ])
            ->firstOrFail();

        $variantsData = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'color' => $variant->color,
            'storage_gb' => $variant->storage_gb,
            'region' => $variant->region,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'availability' => $variant->availability->value,
            'images' => $variant->getMedia('images')->map(fn ($media) => $media->getUrl('large'))->all(),
        ])->values();

        $productImages = $product->getMedia('images')->map(fn ($media) => $media->getUrl('large'))->all();

        $specifications = $product->attributeValues->whereNull('product_variant_id');

        return view('storefront.products.show', compact('product', 'variantsData', 'productImages', 'specifications'));
    }
}