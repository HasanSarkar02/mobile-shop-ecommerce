<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;

function stickyCtaBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function stickyCtaProduct(): array
{
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => 'published']);
    \App\Models\ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create();
    app(InventoryService::class)->restock($variant, 10);

    $slug = $product->translation('en')->slug;

    return [$slug, $variant, $tenant, stickyCtaBase($tenant)];
}

it('renders the mobile sticky purchase bar with its visibility plumbing', function (): void {
    [$slug, $variant, $tenant, $base] = stickyCtaProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('pdp-sticky-cta');
    expect($html)->toContain('lg:hidden fixed bottom-16 inset-x-0 z-40');
    // Sits above the mobile bottom nav and keeps the safe-area approach used elsewhere.
    expect($html)->toContain('bottom-16');
    // Visibility is driven by IntersectionObserver on the buy box + end sentinel.
    expect($html)->toContain('x-ref="buyBox"');
    expect($html)->toContain('x-ref="pdpEnd"');
    expect($html)->toContain('this.buyBoxObserver');
    expect($html)->toContain('stickyCtaVisible');
    expect($html)->toContain('translate-y-full');
});

it('shows both Buy Now and Add to Cart in the sticky bar', function (): void {
    [$slug, $variant, $tenant, $base] = stickyCtaProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // One Buy Now form in the buy box + one in the sticky bar.
    expect(substr_count($html, '/buy-now'))->toBeGreaterThanOrEqual(2);
    expect(substr_count($html, 'Buy Now'))->toBeGreaterThanOrEqual(2);
    // Add to Cart handler exists in the buy box + the sticky bar.
    expect(substr_count($html, 'addToCart()'))->toBeGreaterThanOrEqual(2);
    expect(substr_count($html, 'x-text="ctaLabel()"'))->toBeGreaterThanOrEqual(2);
});

it('binds the sticky CTA to the currently selected variant and quantity', function (): void {
    [$slug, $variant, $tenant, $base] = stickyCtaProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Desktop + sticky Buy Now forms both submit the live selection.
    expect(substr_count($html, ':value="currentVariantId || \'\'"'))->toBeGreaterThanOrEqual(2);
    expect(substr_count($html, ':value="quantity"'))->toBeGreaterThanOrEqual(2);
});

it('gates the sticky CTAs on the same purchasable state as the buy box', function (): void {
    [$slug, $variant, $tenant, $base] = stickyCtaProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Sticky Buy Now + sticky Add to Cart both gate on !current().purchasable.
    expect(substr_count($html, '!current().purchasable'))->toBeGreaterThanOrEqual(4);
    // The sticky Add to Cart keeps the exact shared disabled binding.
    expect($html)->toContain('x-bind:disabled="cartLoading || !current().purchasable"');
});

it('retracts when the end of the product content is reached', function (): void {
    [$slug, $variant, $tenant, $base] = stickyCtaProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Two observers: one on the buy box, one on the end sentinel that hides it.
    expect($html)->toContain('new IntersectionObserver');
    expect($html)->toContain('entry.isIntersecting) {');
    expect($html)->toContain('this.stickyCtaVisible = false;');
});

it('keeps the completed PDP structure intact', function (): void {
    [$slug, $variant, $tenant, $base] = stickyCtaProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Unconditional, always-server-rendered structure markers stay in place.
    expect($html)->toContain('id="specifications"');
    expect($html)->toContain('scroll-mt-32');
    expect($html)->toContain('x-data="productDetail(');
    expect($html)->toContain('x-ref="buyBox"');
});