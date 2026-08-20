<?php

declare(strict_types=1);

use App\Enums\FulfillmentStrategy;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;

function variantSelectionBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function variantSelectionProduct(array $variantOverrides = []): array
{
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create($variantOverrides);
    app(InventoryService::class)->restock($variant, 10);

    return [$product, $variant, $tenant, variantSelectionBase($tenant)];
}

it('requires explicit option selection on a multi-active-variant product', function (): void {
    [$product, $first, $tenant, $base] = variantSelectionProduct(['storage_gb' => 128]);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // No auto-resolution: the initial variant id is null for multi-option
    // products and the component is told selection is required.
    expect($html)->toContain('null, false, false, [], true)');
    expect($html)->toContain('requiresSelection');
    // The old "first variant as default" fallback is gone.
    expect($html)->not->toContain('?? this.variants[0] ?? null');
    // The component derives options only from active variants.
    expect($html)->toContain('activeVariants().map(v => v.dims[code])');
});

it('shows the exact missing dimensions until the selection is complete', function (): void {
    [$product, $first, $tenant, $base] = variantSelectionProduct(['storage_gb' => 128]);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('selectionMessage()');
    expect($html)->toContain("'Please select ' + missing.join(' and ')");
    expect($html)->toContain("'This combination of options is not available.'");
    // The message is wired into the buy box and the sticky bar.
    expect(substr_count($html, 'x-text="selectionMessage()"'))->toBeGreaterThanOrEqual(2);
});

it('disables the purchase CTAs until a concrete variant resolves', function (): void {
    [$product, $first, $tenant, $base] = variantSelectionProduct(['storage_gb' => 128]);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Buy Now submit guard + disabled bindings all gate on a resolved current().
    expect($html)->toContain('if (!current() || !current().purchasable) { $event.preventDefault(); return; }');
    expect($html)->toContain('x-bind:disabled="pending || cartLoading || !current() || !current().purchasable"');
    expect($html)->toContain('x-bind:disabled="cartLoading || !current() || !current().purchasable"');
});

it('keeps the sticky bar visible with disabled CTAs when selection is incomplete', function (): void {
    [$product, $first, $tenant, $base] = variantSelectionProduct(['storage_gb' => 128]);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('x-if="showSticky()"');
    expect($html)->toContain('this.current() !== null || (this.requiresSelection && ! this.selectionComplete())');
});

it('auto-resolves a single active variant and keeps purchase enabled', function (): void {
    [$product, $variant, $tenant, $base] = variantSelectionProduct();
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Single active variant -> initial id passed, selection not required.
    expect($html)->toContain($variant->id.', false, false, [], false)');
    expect($html)->toContain('activeVariants().find(v => v.id === this.currentVariantId) ?? null');
});

it('auto-resolves the single active variant even when other variants are inactive', function (): void {
    [$product, $variant, $tenant, $base] = variantSelectionProduct();
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256, 'is_active' => false]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain($variant->id.', false, false, [], false)');
    expect($html)->toContain('dimensionOptions');
});

it('keeps the gallery on the product-level images until a variant resolves', function (): void {
    [$product, $first, $tenant, $base] = variantSelectionProduct(['storage_gb' => 128]);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('currentImages()');
    expect($html)->toContain('if (! variant) return this.productImages;');
    // The no-media placeholder icon is present in the gallery.
    expect($html)->toContain('x-show="!galleryLoaded"');
    expect($html)->toContain('M2.25 15.75l5.159-5.159');
});

it('serializes variant and product gallery images for the Alpine payload', function (): void {
    [$product, $first, $tenant, $base] = variantSelectionProduct(['storage_gb' => 128]);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // The serialized variant payload always carries the images bucket used by
    // currentImages() for the variant gallery / product fallback.
    expect($html)->toContain('productDetail(');
    expect($html)->toContain('\\u0022images\\u0022');
    expect($html)->toContain('currentImages()');
});

it('serializes is_active so the client can distinguish purchasable surfaces', function (): void {
    [$product, $first, $tenant, $base] = variantSelectionProduct(['storage_gb' => 128]);
    ProductVariant::factory()->for($product)->create(['storage_gb' => 256, 'is_active' => false]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // Both active and inactive variants carry the flag in the Alpine payload.
    expect($html)->toContain('\\u0022is_active\\u0022:true');
    expect($html)->toContain('\\u0022is_active\\u0022:false');
});

it('keeps a preorder single variant auto-resolved and purchasable', function (): void {
    [$product, $variant, $tenant, $base] = variantSelectionProduct([
        'inventory_type' => 'not_tracked',
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
    ]);
    $slug = $product->translation('en')->slug;

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain($variant->id.', false, false, [], false)');
    expect($html)->toContain('Pre-Order Now');
});
