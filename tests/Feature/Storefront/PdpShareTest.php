<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;

function shareProduct(): array
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

it('renders an SSR-safe share button in the buy box actions', function (): void {
    [$slug, $base] = shareProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('aria-label="Share product"');
    expect($html)->toContain('@click="share()"');
    expect($html)->toContain('x-show="!shareLoading"');
});

it('uses the Web Share API with a clipboard fallback when unavailable', function (): void {
    [$slug, $base] = shareProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('navigator.share');
    expect($html)->toContain('navigator.clipboard.writeText');
    expect($html)->toContain('document.execCommand(\'copy\')');
});

it('shares the canonical URL', function (): void {
    [$slug, $base, $product] = shareProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('rel="canonical"');
});
