<?php

declare(strict_types=1);

use App\Enums\FulfillmentStrategy;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\Storefront\ProductCardData;
use App\Support\Tenancy\TenantUrlGenerator;

function ctaBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function ctaProduct(array $productOverrides = [], array $variantOverrides = []): Product
{
    $product = Product::factory()->create(array_merge(['status' => ProductStatus::Published], $productOverrides));
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create($variantOverrides);
    app(InventoryService::class)->restock($variant, 10);

    return $product;
}

function ctaView(Product $product): array
{
    $product->load('translations', 'variants', 'media', 'emiPlans');

    return app(ProductCardData::class)->forMany(collect([$product]), collect())->first();
}

it('resolves Add to Cart for a single active purchasable variant', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = ctaProduct();
    $card = ctaView($product);

    $variant = $product->variants->first();

    expect($card['requires_selection'])->toBeFalse();
    expect($card['cta']['type'])->toBe('add_to_cart');
    expect($card['cta']['label'])->toBe('Add to Cart');
    expect($card['cta']['variant_id'])->toBe($variant->id);
    expect($card['cta']['disabled'])->toBeFalse();
});

it('resolves Pre-Order for a single active preorder variant', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = ctaProduct([], [
        'inventory_type' => 'not_tracked',
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
    ]);
    $card = ctaView($product);

    expect($card['cta']['type'])->toBe('add_to_cart');
    expect($card['cta']['label'])->toBe('Pre-Order');
});

it('resolves Backorder for an out-of-stock variant with notify backorder', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create([
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'notify',
    ]);

    $card = ctaView($product);

    expect($card['cta']['type'])->toBe('add_to_cart');
    expect($card['cta']['label'])->toBe('Backorder');
});

it('disables the CTA when the only active variant is out of stock', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create([
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'deny',
    ]);

    $card = ctaView($product);

    expect($card['cta']['type'])->toBe('disabled');
    expect($card['cta']['label'])->toBe('Out of Stock');
    expect($card['cta']['disabled'])->toBeTrue();
    expect($card['cta']['variant_id'])->toBeNull();
});

it('renders no CTA for a discontinued variant', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['availability' => 'discontinued']);

    $card = ctaView($product);

    expect($card['cta']['type'])->toBe('none');
    expect($card['cta']['disabled'])->toBeTrue();
});

it('renders no CTA when the product has no active variants', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['is_active' => false]);

    $card = ctaView($product);

    expect($card['cta']['type'])->toBe('none');
    expect($card['variant'])->toBeNull();
});

it('routes multi-active-variant products to Select Options', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 128]);
    $second = ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    app(InventoryService::class)->restock($second, 10);

    $card = ctaView($product);

    expect($card['requires_selection'])->toBeTrue();
    expect($card['cta']['type'])->toBe('select_options');
    expect($card['cta']['label'])->toBe('Select Options');
    expect($card['cta']['variant_id'])->toBeNull();
    expect($card['cta']['disabled'])->toBeFalse();
    expect($card['cta']['url'])->toBe(app(TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.product', [$product->translation('en')->slug]));
});

it('renders the async Add to Cart button on the card for a single purchasable variant', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = ctaProduct();
    $variant = $product->variants->first();

    $html = view('storefront.partials.product-card', ['card' => ctaView($product)])->render();

    expect($html)->toContain('$store.cart.add('.$variant->id.')');
    expect($html)->toContain('$store.cart.pending['.$variant->id.']');
    expect($html)->toContain('Add to Cart');
    // The button must be a sibling of the wrapping <a>, never nested inside it.
    expect(substr_count($html, '</a>'))->toBe(1);
});

it('renders the Select Options link for a multi-active-variant product', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 128]);
    $second = ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    app(InventoryService::class)->restock($second, 10);

    $html = view('storefront.partials.product-card', ['card' => ctaView($product)])->render();

    expect($html)->toContain('Select Options');
    expect($html)->toContain(app(TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.product', [$product->translation('en')->slug]));
    expect($html)->not->toContain('$store.cart.add(');
});

it('renders a disabled CTA for an out-of-stock product', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create([
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'deny',
    ]);

    $html = view('storefront.partials.product-card', ['card' => ctaView($product)])->render();

    expect($html)->toContain('Out of Stock');
    expect($html)->toContain('disabled');
});

it('exposes the cart-store endpoint on the storefront layout body', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $base = ctaBase($tenant);

    $html = $this->get($base.'/')->assertOk()->getContent();

    expect($html)->toContain('data-cart-store="'.route('storefront.cart.store').'"');
});
