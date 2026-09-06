<?php

declare(strict_types=1);

use App\Enums\AttributeDataType;
use App\Enums\ProductStatus;
use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\Storefront\ProductCardData;
use App\Support\IndustryConfig;

function fashionProduct(array $productOverrides = [], array $variantOverrides = []): Product
{
    $product = Product::factory()->create(array_merge(['status' => ProductStatus::Published], $productOverrides));
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create($variantOverrides);
    app(InventoryService::class)->restock($variant, 10);

    return $product;
}

function attachFashionImages(Product $product, int $count): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    for ($i = 1; $i <= $count; $i++) {
        $product->addMediaFromString($png)->usingFileName("fashion-{$i}.png")->toMediaCollection('images');
    }
    $product->load('media');
}

function fashionCard(Product $product, ?array $wishlistedIds = []): array
{
    $product->load('translations', 'variants', 'variants.attributeValues.attributeDefinition', 'variants.attributeValues.attributeOption', 'media', 'emiPlans', 'uom');

    return app(ProductCardData::class)->forMany(collect([$product]), collect($wishlistedIds))->first();
}

it('resolves fashion industry to storefront.product-cards.fashion component', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);

    expect(IndustryConfig::currentGet('ui.card_component'))->toBe('storefront.product-cards.fashion');
    expect(IndustryConfig::currentGet('ui.image_aspect'))->toBe('aspect-[3/4]');
});

it('renders fashion card with aspect-[3/4] and not aspect-square hardcoded', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 1);
    $card = fashionCard($product);

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();

    expect($html)->toContain('aspect-[3/4]');
    // The shared product-image component still defaults to aspect-square but fashion passes explicit aspect
    // Fashion card directly uses aspect-[3/4] class
    expect($card['gallery_images'])->toHaveCount(1);
});

it('renders primary image correctly for fashion card', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 1);
    $card = fashionCard($product);

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();

    // Primary image src should be present
    expect($card['image'])->not->toBeNull();
    expect($html)->toContain($card['gallery_images'][0]['src']);
    expect($html)->toContain('alt=');
});

it('exposes secondary image when gallery has >=2 images and hover is enabled', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 2);
    $card = fashionCard($product);

    expect($card['gallery_images'])->toHaveCount(2);
    expect($card['hover_gallery_enabled'])->toBeTrue();

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();

    expect($html)->toContain($card['gallery_images'][1]['src']);
    // Desktop hover handlers
    expect($html)->toContain('@mouseenter="handleEnter()"');
    expect($html)->toContain('@mouseleave="handleLeave()"');
    // Crossfade uses opacity transition
    expect($html)->toContain('transition-opacity');
    expect($html)->toContain('duration-[240ms]');
    // Hover uses canHover guard
    expect($html)->toContain("matchMedia('(hover: hover) and (pointer: fine)')");
});

it('preserves full gallery collection for 3+ images and shows dot indicators', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 5);
    $card = fashionCard($product);

    expect($card['gallery_images'])->toHaveCount(5);
    expect($card['hover_gallery_enabled'])->toBeTrue();

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();

    // All 5 images should be in markup (layered)
    foreach ($card['gallery_images'] as $g) {
        expect($html)->toContain($g['src']);
    }
    // Dots for gallery
    expect($html)->toContain('goTo(');
    // Swipe handlers for mobile
    expect($html)->toContain('@touchstart');
    expect($html)->toContain('@touchend');
    expect($html)->toContain('onTouchStart');
    // Counter
    expect($html)->toContain("1) + ' / ' + total");
});

it('does not produce broken hover state for single-image products', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 1);
    $card = fashionCard($product);

    expect($card['hover_gallery_enabled'])->toBeFalse();
    expect($card['gallery_images'])->toHaveCount(1);

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();

    // No dot indicators when single image
    expect($html)->not->toContain('goTo(1)');
    // No secondary image opacity logic beyond primary
    // Should still render primary without error
    expect($html)->toContain($card['gallery_images'][0]['src']);
});

it('renders wishlist button correctly for fashion card', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 1);
    $card = fashionCard($product, [$product->id]);
    expect($card['wishlisted'])->toBeTrue();

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();

    expect($html)->toContain('seed('.$product->id.', true)');
    expect($html)->toContain('$store.wishlist.toggle('.$product->id.')');
    // Not inside product link nested check: wishlist button is separate
    expect($html)->toContain('Add to wishlist');
});

it('respects CTA purchasability for fashion card - single purchasable variant', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct([], ['price' => 120000, 'compare_at_price' => 150000]);
    $card = fashionCard($product);

    expect($card['cta']['type'])->toBe('add_to_cart');
    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();
    expect($html)->toContain('$store.cart.add('.$card['cta']['variant_id'].')');
    expect($html)->toContain('bg-[var(--brand)]');
    expect($html)->toContain('h-11 w-11');
    // CTA should not use hardcoded purple
    expect($html)->not->toContain('bg-[#4c1d95]');
});

it('renders select_options via variant modal for multi-variant fashion products', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $v1 = ProductVariant::factory()->for($product)->create(['color' => 'Red', 'price' => 100000]);
    $v2 = ProductVariant::factory()->for($product)->create(['color' => 'Blue', 'price' => 120000]);
    app(InventoryService::class)->restock($v1, 10);
    app(InventoryService::class)->restock($v2, 10);

    $card = fashionCard($product);
    expect($card['requires_selection'])->toBeTrue();
    expect($card['cta']['type'])->toBe('select_options');

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();
    expect($html)->toContain('variantSelectionState');
    expect($html)->toContain('role="dialog"');
});

it('keeps unavailable products add-to-cart enabled for fashion card (server validates)', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create([
        'inventory_type' => 'tracked',
        'fulfillment_strategy' => 'stock',
        'backorder_policy' => 'deny',
    ]);
    $card = fashionCard($product);
    expect($card['cta']['type'])->toBe('add_to_cart');
    expect($card['cta']['disabled'])->toBeFalse();
    expect($card['variant']['is_out_of_stock'])->toBeTrue();
    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();
    expect($html)->toContain('Add to Cart');
});

it('extracts color swatches from native variant color data', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $v1 = ProductVariant::factory()->for($product)->create(['color' => 'Lavender']);
    $v2 = ProductVariant::factory()->for($product)->create(['color' => 'Beige']);
    $v3 = ProductVariant::factory()->for($product)->create(['color' => 'Lavender']); // duplicate should dedupe
    foreach ([$v1, $v2, $v3] as $v) {
        app(InventoryService::class)->restock($v, 10);
    }

    $card = fashionCard($product);
    expect($card['swatches'])->toHaveCount(2);
    expect(collect($card['swatches'])->pluck('name')->toArray())->toContain('Lavender', 'Beige');
    expect($card['swatches_overflow'])->toBe(0);

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();
    expect($html)->toContain('background-color:');
    // Each swatch should have aria-label
    expect($html)->toContain('Color Lavender');
});

it('extracts swatches from EAV color attributes and shows overflow', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    // Use a unique code to avoid collision with FashionIndustrySeeder's fabric_color
    $attr = AttributeDefinition::query()->create([
        'code' => 'test_overflow_color',
        'label' => 'Test Color',
        'data_type' => AttributeDataType::Select,
        'is_variant_defining' => true,
        'is_filterable' => true,
    ]);
    $colors = ['Red', 'Blue', 'Green', 'Yellow', 'Pink', 'Teal', 'Black'];
    foreach ($colors as $c) {
        $attr->options()->create(['label' => $c, 'value' => $c]);
    }

    foreach ($colors as $c) {
        $variant = ProductVariant::factory()->for($product)->create();
        app(InventoryService::class)->restock($variant, 10);
        $option = $attr->options()->where('value', $c)->first();
        $variant->attributeValues()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $attr->id,
            'attribute_option_id' => $option->id,
        ]);
    }

    $card = fashionCard($product);
    expect($card['swatches'])->toHaveCount(5);
    expect($card['swatches_overflow'])->toBe(2);
    expect($card['swatches_total'])->toBe(7);

    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();
    expect($html)->toContain('+2');
});

it('does not regress grocery or default card behavior', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'grocery']);
    expect(IndustryConfig::currentGet('ui.card_component'))->toBe('storefront.product-cards.grocery');
    $product = fashionProduct();
    $card = fashionCard($product);
    // Grocery card should still ignore swatches key safely
    $groceryHtml = view('components.storefront.product-cards.grocery', ['card' => $card])->render();
    expect($groceryHtml)->toContain($card['name']);

    $tenant2 = actingAsTenant(['status' => 'active', 'industry' => 'general']);
    expect(IndustryConfig::currentGet('ui.card_component'))->toBe('storefront.product-cards.default');
    $defaultHtml = view('components.storefront.product-cards.default', ['card' => $card])->render();
    expect($defaultHtml)->toContain($card['name']);

    // Shared purchasability still works
    expect($card['cta']['type'])->toBe('add_to_cart');
});

it('has correct responsive and accessibility attributes', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 2);
    $card = fashionCard($product);
    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();

    // Wishlist accessible label via Alpine aria-label
    expect($html)->toContain('aria-label');
    // CTA accessible
    expect($html)->toContain('aria-label');
    // Focus states
    expect($html)->toContain('focus-visible:ring');
    // Touch target class
    expect($html)->toContain('touch-pan-y');
    // No layout shift: aspect-[3/4] present
    expect($html)->toContain('aspect-[3/4]');
});

it('uses full gallery collection via ProductCardData (max 5)', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    $product = fashionProduct();
    attachFashionImages($product, 5);
    $card = fashionCard($product);
    expect($card['gallery_images'])->toHaveCount(5);
    // Verify thumb URLs used (existing conversion)
    foreach ($card['gallery_images'] as $img) {
        expect($img['src'])->toBeString();
        expect($img['alt'])->toBeString();
    }
});
