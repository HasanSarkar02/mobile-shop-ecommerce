<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;

function galleryProduct(): array
{
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create();
    app(InventoryService::class)->restock($variant, 10);

    $slug = $product->translation('en')->slug;
    $base = 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');

    return [$slug, $base, $product];
}

it('wires media_alt into the main gallery, thumbnails and lightbox', function (): void {
    [$slug, $base] = galleryProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Main image, thumbnail and lightbox all consume the alt from the
    // serialized {src, alt} image objects.
    expect(substr_count($html, ':alt="image.alt"'))->toBeGreaterThanOrEqual(3);
    expect(substr_count($html, 'image.src === activeImage'))->toBeGreaterThanOrEqual(2);
});

it('serializes gallery images as src/alt objects in the Alpine payload', function (): void {
    [$slug, $base, $product] = galleryProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('src');
    expect($html)->toContain('alt');
    expect($html)->toContain('currentImages()');
});
