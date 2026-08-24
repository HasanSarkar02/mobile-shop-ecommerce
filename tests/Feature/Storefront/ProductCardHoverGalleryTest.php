<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\Storefront\ProductCardData;

function hoverProduct(array $overrides = []): Product
{
    $product = Product::factory()->create(array_merge(['status' => ProductStatus::Published], $overrides));
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create();
    app(InventoryService::class)->restock($variant, 10);

    return $product;
}

function attachImages(Product $product, int $count): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    for ($i = 1; $i <= $count; $i++) {
        $product->addMediaFromString($png)->usingFileName("hover-{$i}.png")->toMediaCollection('images');
    }
    $product->load('media');
}

function hoverCard(Product $product): array
{
    $product->load('translations', 'variants', 'media', 'emiPlans');

    return app(ProductCardData::class)->forMany(collect([$product]), collect())->first();
}

it('keeps hover preview disabled by default (general preset)', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = hoverProduct();
    attachImages($product, 2);

    $card = hoverCard($product);

    expect($card['hover_gallery_enabled'])->toBeFalse();
    expect($card['gallery_images'])->toHaveCount(2);
});

it('enables hover preview when the per-industry toggle is on and multiple images exist', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    // Simulate a fashion storefront that has opted in (F.6 never auto-enables,
    // so this is the explicit per-storefront/industry toggle).
    config()->set('industries.presets.fashion.card.hover_gallery_enabled', true);
    $tenant->setAttribute('industry', 'fashion');

    $product = hoverProduct();
    attachImages($product, 3);

    $card = hoverCard($product);

    expect($card['hover_gallery_enabled'])->toBeTrue();
    expect($card['gallery_images'])->toHaveCount(3);
});

it('keeps hover disabled when only one image exists even if the industry toggle is on', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    config()->set('industries.presets.fashion.card.hover_gallery_enabled', true);
    $tenant->setAttribute('industry', 'fashion');

    $product = hoverProduct();
    attachImages($product, 1);

    $card = hoverCard($product);

    expect($card['hover_gallery_enabled'])->toBeFalse();
});

it('renders hover-only markup on desktop and restores primary on mouse-leave', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    config()->set('industries.presets.fashion.card.hover_gallery_enabled', true);
    $tenant->setAttribute('industry', 'fashion');

    $product = hoverProduct();
    attachImages($product, 2);

    $html = view('storefront.partials.product-card', ['card' => hoverCard($product)])->render();

    // Desktop-only guard: touch devices never trigger the timer.
    expect($html)->toContain("matchMedia('(hover: hover) and (pointer: fine)')");
    // Hover handlers: mouse-enter starts cycling, mouse-leave restores primary.
    expect($html)->toContain('@mouseenter="startHover()"');
    expect($html)->toContain('@mouseleave="stopHover()"');
    expect($html)->toContain('hoverIndex');
    // Secondary image is layered absolutely and hidden until hover.
    expect($html)->toContain('absolute inset-0');
    // Primary image is always the fallback (visible when hoverIndex === 0).
    expect($html)->toContain('hoverIndex === 0');
});

it('does not render hover preview markup when disabled', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = hoverProduct();
    attachImages($product, 2);

    $html = view('storefront.partials.product-card', ['card' => hoverCard($product)])->render();

    // No secondary layered images when the feature is off — only the single
    // primary <img> and the shared skeleton/error states.
    expect($html)->not->toContain('hoverIndex === 1');
});
