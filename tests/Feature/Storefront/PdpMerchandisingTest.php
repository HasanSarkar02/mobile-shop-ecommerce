<?php

declare(strict_types=1);

use App\Enums\ProductRelationType;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\InventoryService;
use App\Support\Tenancy\Tenancy;

function merchandisingBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function merchandisingProduct(string $modelNumber, string $status = 'published'): Product
{
    $product = Product::factory()->create(['status' => $status, 'model_number' => $modelNumber]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create();
    app(InventoryService::class)->restock($variant, 10);

    return $product;
}

function createMerchandiseRelation(Product $product, Product $related, ProductRelationType $type, int $sortOrder = 1): ProductRelation
{
    return ProductRelation::query()->create([
        'tenant_id' => $product->tenant_id,
        'product_id' => $product->id,
        'related_product_id' => $related->id,
        'type' => $type,
        'sort_order' => $sortOrder,
    ]);
}

it('renders all four merchandising rails when each relation is populated', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $current = merchandisingProduct('MER-CUR');
    $base = merchandisingBase($tenant);

    $crossSell = merchandisingProduct('MER-CXS');
    $upsell = merchandisingProduct('MER-UP');
    $fbt = merchandisingProduct('MER-FBT');
    $compatible = merchandisingProduct('MER-COM');

    createMerchandiseRelation($current, $crossSell, ProductRelationType::CrossSell);
    createMerchandiseRelation($current, $upsell, ProductRelationType::Upsell);
    createMerchandiseRelation($current, $fbt, ProductRelationType::FrequentlyBoughtTogether);
    createMerchandiseRelation($current, $compatible, ProductRelationType::Compatible);

    $html = $this->get($base.'/product/'.$current->translation('en')->slug)->assertOk()->getContent();

    expect($html)->toContain('id="cross-sells"');
    expect($html)->toContain('id="upsells"');
    expect($html)->toContain('id="frequently-bought-together"');
    expect($html)->toContain('id="compatible-accessories"');

    expect($html)->toContain('/product/'.$crossSell->translation('en')->slug);
    expect($html)->toContain('/product/'.$upsell->translation('en')->slug);
    expect($html)->toContain('/product/'.$fbt->translation('en')->slug);
    expect($html)->toContain('/product/'.$compatible->translation('en')->slug);
});

it('renders nothing for empty merchandising rails', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $current = merchandisingProduct('MER-EMP');
    $base = merchandisingBase($tenant);

    $html = $this->get($base.'/product/'.$current->translation('en')->slug)->assertOk()->getContent();

    expect($html)->not->toContain('id="cross-sells"');
    expect($html)->not->toContain('id="upsells"');
    expect($html)->not->toContain('id="frequently-bought-together"');
    expect($html)->not->toContain('id="compatible-accessories"');
    expect($html)->not->toContain('>Upgrade Your Choice</h2>');
    expect($html)->not->toContain('>Frequently Bought Together</h2>');
    expect($html)->not->toContain('>Compatible Accessories</h2>');
});

it('excludes unpublished products from merchandising rails', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $current = merchandisingProduct('MER-PUB');
    $base = merchandisingBase($tenant);

    $draft = merchandisingProduct('MER-DRAFT', 'draft');
    createMerchandiseRelation($current, $draft, ProductRelationType::CrossSell);

    $html = $this->get($base.'/product/'.$current->translation('en')->slug)->assertOk()->getContent();

    expect($html)->not->toContain('/product/'.$draft->translation('en')->slug);
    expect($html)->not->toContain('id="cross-sells"');
});

it('excludes the current product from its own merchandising rails', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $current = merchandisingProduct('MER-SELF');
    $base = merchandisingBase($tenant);

    createMerchandiseRelation($current, $current, ProductRelationType::CrossSell);

    $html = $this->get($base.'/product/'.$current->translation('en')->slug)->assertOk()->getContent();

    expect($html)->not->toContain('id="cross-sells"');
});

it('never shows cross-tenant related products', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $base = merchandisingBase($tenant);

    $current = merchandisingProduct('MER-TA');

    $foreign = Tenant::factory()->create(['status' => 'active']);
    app(Tenancy::class)->set($foreign);
    $foreignProduct = Product::factory()->create([
        'tenant_id' => $foreign->id,
        'status' => 'published',
        'model_number' => 'MER-TB',
    ]);
    ProductTranslation::factory()->for($foreignProduct)->create(['locale' => 'en']);
    $foreignVariant = ProductVariant::factory()->for($foreignProduct)->create();
    app(InventoryService::class)->restock($foreignVariant, 10);
    $foreignSlug = $foreignProduct->translation('en')->slug;
    app(Tenancy::class)->set($tenant);

    createMerchandiseRelation($current, $foreignProduct, ProductRelationType::Upsell);

    $html = $this->get($base.'/product/'.$current->translation('en')->slug)->assertOk()->getContent();

    expect($html)->not->toContain('/product/'.$foreignSlug);
    expect($html)->not->toContain('id="upsells"');
});
