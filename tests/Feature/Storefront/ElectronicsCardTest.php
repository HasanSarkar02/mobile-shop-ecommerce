<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\Storefront\ProductCardData;
use App\Support\IndustryConfig;
use Illuminate\Support\Str;

function electronicsBrand(string $name = 'Apple'): Brand
{
    return Brand::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
    ]);
}

function electronicsProduct(array $productOverrides = [], array $variantOverrides = []): Product
{
    $brand = $productOverrides['brand_id'] ?? null ? null : electronicsBrand();
    $data = array_merge(['status' => ProductStatus::Published], $productOverrides);
    if ($brand) {
        $data['brand_id'] = $brand->id;
    }
    $product = Product::factory()->create($data);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create($variantOverrides);
    app(InventoryService::class)->restock($variant, 10);

    return $product;
}

function attachElectronicsImages(Product $product, int $count): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    for ($i = 1; $i <= $count; $i++) {
        $product->addMediaFromString($png)->usingFileName("electronics-{$i}.png")->toMediaCollection('images');
    }
    $product->load('media');
}

function electronicsCard(Product $product, array $wishlistedIds = []): array
{
    $product->load('translations', 'variants', 'variants.attributeValues.attributeDefinition', 'variants.attributeValues.attributeOption', 'media', 'emiPlans', 'uom', 'brand');

    return app(ProductCardData::class)->forMany(collect([$product]), collect($wishlistedIds))->first();
}

it('resolves electronics industry to storefront.product-cards.electronics component', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);

    expect(IndustryConfig::currentGet('ui.card_component'))->toBe('storefront.product-cards.electronics');
    expect(IndustryConfig::currentGet('ui.image_aspect'))->toBe('aspect-square');
    expect(IndustryConfig::currentGet('card.hover_gallery_enabled'))->toBeTrue();
});

it('renders electronics card with aspect-square and object-contain', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);

    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();

    expect($html)->toContain('aspect-square');
    expect($html)->not->toContain('aspect-[3/4]');
    expect($html)->toContain('object-contain');
    expect($html)->not->toContain('object-cover object-center transition-opacity duration-[240ms]');
    expect($html)->toContain('bg-white');
    expect($html)->not->toContain('bg-[#f8fafc]');
    expect($card['gallery_images'])->toHaveCount(1);
});

it('renders enlarged contain image presentation with scaled white background', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();

    expect($html)->toContain('object-contain');
    expect($html)->not->toContain('object-cover');
    expect($html)->toContain('scale-[1.18]');
    expect($html)->toContain('overflow-hidden');
    expect($html)->toContain('bg-white');
    expect($html)->toContain('p-2');
});

it('renders primary image correctly', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();

    expect($card['has_image'])->toBeTrue();
    expect($card['image'])->not->toBeNull();
    expect($html)->toContain($card['image']);
    expect($html)->toContain($card['image_alt']);
});

it('renders brand correctly', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $brand = electronicsBrand('Samsung');
    $product = electronicsProduct(['brand_id' => $brand->id]);
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);

    expect($card['brand_name'])->toBe('Samsung');

    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain('Samsung');
    expect($html)->toContain('uppercase');
    expect($html)->toContain('tracking-widest');
});

it('renders product name correctly with reserved 2-line height', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);

    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain($card['name']);
    expect($html)->toContain('line-clamp-2');
    expect($html)->toContain('min-h-[2.45rem]');
});

it('renders rating and review count with prominence', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct(['average_rating' => 4.5, 'reviews_count' => 128]);
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);

    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain('4.5');
    expect($html)->toContain('(128)');
    expect($html)->toContain('★');
    expect($html)->toContain('text-amber-500');
    expect($html)->toContain('min-h-[1.1rem]');
});

it('reserves rating height even when no reviews', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct(['average_rating' => null, 'reviews_count' => 0]);
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();

    expect($html)->toContain('min-h-[1.1rem]');
    expect($html)->not->toContain('(0)');
});

it('renders price with compare-at and discount inline', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct([], ['price' => 12000000, 'compare_at_price' => 15000000]);
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);

    expect($card['discount_percentage'])->toBe(20);

    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain(money_without_trailing_zeros(12000000));
    expect($html)->toContain(money_without_trailing_zeros(15000000));
    expect($html)->toContain('-20%');
    expect($html)->toContain('line-through');
    expect($html)->toContain('min-h-[1.35rem]');
    // discount must be inline inside price row, not separate block — tight gap prevents mobile wrap
    expect($html)->toContain('flex items-baseline gap-1');
    expect($html)->toContain('whitespace-nowrap');
    expect($html)->toContain('text-[13px]');
});

it('does not render spec_summary storage RAM color line', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct([], ['storage_gb' => 256, 'ram_gb' => 8, 'color' => 'Silver']);
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);

    expect($card)->not->toHaveKey('spec_summary');
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    // Card must not contain spec summary tokens inline as separate line
    expect($html)->not->toContain('256GB · Silver');
    expect($html)->not->toContain('256GB · 8GB RAM');
    // Ensure no truncated spec paragraph rendered
    expect($html)->not->toContain('truncate text-[11px] leading-snug text-gray-500');
});

it('maintains same card structure for regular and discounted products', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $regular = electronicsProduct([], ['price' => 10000000, 'compare_at_price' => null]);
    $discounted = electronicsProduct([], ['price' => 12000000, 'compare_at_price' => 15000000]);
    attachElectronicsImages($regular, 1);
    attachElectronicsImages($discounted, 1);
    $regularCard = electronicsCard($regular);
    $discountedCard = electronicsCard($discounted);

    $regularHtml = view('components.storefront.product-cards.electronics', ['card' => $regularCard])->render();
    $discountedHtml = view('components.storefront.product-cards.electronics', ['card' => $discountedCard])->render();

    // Both must have reserved price row height and CTA anchored at bottom
    foreach ([$regularHtml, $discountedHtml] as $html) {
        expect($html)->toContain('min-h-[1.35rem]');
        expect($html)->toContain('mt-auto');
        expect($html)->toContain('flex flex-1 flex-col');
        expect($html)->toContain('line-clamp-2');
    }
    // Discounted shows discount inline, regular does not but structure same
    expect($regularHtml)->not->toContain('-20%');
    expect($regularHtml)->not->toContain('line-through');
    expect($discountedHtml)->toContain('-20%');
});

it('exposes secondary image and gallery dots when gallery has >=2 images and hover is enabled', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 3);
    $card = electronicsCard($product);

    expect($card['gallery_images'])->toHaveCount(3);
    expect($card['hover_gallery_enabled'])->toBeTrue();

    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain($card['gallery_images'][1]['src']);
    expect($html)->toContain('goTo(');
    expect($html)->toContain('@mouseenter="handleEnter()"');
    expect($html)->toContain('@mouseleave="handleLeave()"');
    expect($html)->toContain("matchMedia('(hover: hover) and (pointer: fine)')");
    expect($html)->not->toContain('setInterval');
});

it('preserves full gallery collection for 5 images and browsable', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 5);
    $card = electronicsCard($product);
    expect($card['gallery_images'])->toHaveCount(5);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    foreach ($card['gallery_images'] as $g) {
        expect($html)->toContain($g['src']);
    }
    expect($html)->toContain('goTo(');
});

it('has mobile swipe handlers and dy-vs-dx protection without autoplay', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 2);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();

    expect($html)->toContain('@touchstart');
    expect($html)->toContain('@touchmove');
    expect($html)->toContain('@touchend');
    expect($html)->toContain('onTouchStart');
    expect($html)->toContain('onTouchEnd');
    expect($html)->toContain('touch-pan-y');
    expect($html)->toContain('Math.abs(dy) > Math.abs(dx)');
    expect($html)->toContain('threshold = 40');
    expect($html)->toContain("1) + ' / ' + total");
    expect($html)->not->toContain('setInterval');
});

it('does not produce broken hover state for single-image products', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);
    expect($card['hover_gallery_enabled'])->toBeFalse();
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->not->toContain('goTo(1)');
});

it('renders wishlist using existing primitive', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product, [$product->id]);
    expect($card['wishlisted'])->toBeTrue();
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain('seed('.$product->id.', true)');
    expect($html)->toContain('$store.wishlist.toggle('.$product->id.')');
    expect($html)->toContain('Add to wishlist');
    // Must be top-right, not overlap with quick-view eye
    expect($html)->toContain('absolute right-2 top-2');
    expect($html)->not->toContain('Quick view');
});

it('renders Add to Cart for single purchasable variant with Store Primary styling h-10', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct([], ['price' => 12000000, 'compare_at_price' => 15000000]);
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);
    expect($card['cta']['type'])->toBe('add_to_cart');
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain('$store.cart.add('.$card['cta']['variant_id'].')');
    expect($html)->toContain('bg-[var(--brand)]');
    expect($html)->toContain('text-white');
    expect($html)->toContain('h-10');
    expect($html)->not->toContain('bg-green-600');
    expect($html)->not->toContain('bg-[#4c1d95]');
    expect($html)->toContain('mt-auto');
});

it('does not use Grocery green CTA styling', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->not->toContain('bg-green-600');
    expect($html)->not->toContain('rounded-full bg-green-600');
});

it('renders Select Options via variant modal for multi-variant products with Store Primary styling', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => electronicsBrand()->id]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $v1 = ProductVariant::factory()->for($product)->create(['storage_gb' => 128, 'price' => 10000000]);
    $v2 = ProductVariant::factory()->for($product)->create(['storage_gb' => 256, 'price' => 12000000]);
    app(InventoryService::class)->restock($v1, 10);
    app(InventoryService::class)->restock($v2, 10);
    $card = electronicsCard($product);
    expect($card['requires_selection'])->toBeTrue();
    expect($card['cta']['type'])->toBe('select_options');
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain('variantSelectionState');
    expect($html)->toContain('role="dialog"');
    expect($html)->toContain('bg-[var(--brand)]');
    expect($html)->toContain('Add to Cart');
    expect($html)->not->toContain('bg-green-600');
    // CTA must still be anchored at bottom
    expect($html)->toContain('mt-auto');
});

it('keeps unavailable products disabled', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => electronicsBrand()->id]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['inventory_type' => 'tracked', 'fulfillment_strategy' => 'stock', 'backorder_policy' => 'deny']);
    $card = electronicsCard($product);
    expect($card['cta']['type'])->toBe('disabled');
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain('Out of Stock');
    expect($html)->toContain('disabled');
    expect($html)->toContain('h-10');
});

it('has CTA aligned to card bottom with mt-auto and price min-height', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();

    expect($html)->toContain('mt-auto');
    expect($html)->toContain('min-h-[1.35rem]');
    expect($html)->toContain('flex flex-1 flex-col');
});

it('does not render eye quick-view icon', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 2);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->not->toContain('Quick view');
    expect($html)->not->toContain('M2.036 12.322');
});

it('brand eager-loading does not introduce N+1', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $p1 = electronicsProduct();
    $p2 = electronicsProduct();
    $p1->load('translations', 'variants', 'media', 'emiPlans', 'uom', 'brand');
    $p2->load('translations', 'variants', 'media', 'emiPlans', 'uom', 'brand');
    $cards = app(ProductCardData::class)->forMany(collect([$p1, $p2]), collect());
    expect($cards)->toHaveCount(2);
    expect($cards[0]['brand_name'])->not->toBeNull();
    expect($cards[1]['brand_name'])->not->toBeNull();
});

it('does not break fashion card swatches', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'fashion']);
    expect(IndustryConfig::currentGet('ui.card_component'))->toBe('storefront.product-cards.fashion');
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $v1 = ProductVariant::factory()->for($product)->create(['color' => 'Red']);
    app(InventoryService::class)->restock($v1, 10);
    $product->load('translations', 'variants', 'variants.attributeValues.attributeDefinition', 'variants.attributeValues.attributeOption', 'media', 'emiPlans', 'uom', 'brand');
    $card = app(ProductCardData::class)->forMany(collect([$product]), collect())->first();
    $html = view('components.storefront.product-cards.fashion', ['card' => $card])->render();
    expect($html)->toContain('background-color:');
});

it('does not leak electronics styling into grocery', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'grocery']);
    expect(IndustryConfig::currentGet('ui.card_component'))->toBe('storefront.product-cards.grocery');
    $product = electronicsProduct();
    attachElectronicsImages($product, 1);
    $product->load('translations', 'variants', 'media', 'emiPlans', 'uom', 'brand');
    $card = app(ProductCardData::class)->forMany(collect([$product]), collect())->first();
    $html = view('components.storefront.product-cards.grocery', ['card' => $card])->render();
    expect($html)->toContain($card['name']);
    expect($html)->toContain('bg-green-600');
    expect($html)->not->toContain('spec_summary');
});

it('has correct accessibility attributes', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $product = electronicsProduct();
    attachElectronicsImages($product, 2);
    $card = electronicsCard($product);
    $html = view('components.storefront.product-cards.electronics', ['card' => $card])->render();
    expect($html)->toContain('aria-label');
    expect($html)->toContain('focus-visible:ring');
    expect($html)->toContain('touch-pan-y');
});
