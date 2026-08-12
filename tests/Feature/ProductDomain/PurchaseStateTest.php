<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Services\InventoryService;
use Illuminate\Testing\TestResponse;

/**
 * Renders the product page the same way the storefront does and returns the
 * SSR HTML. `@js()` escapes JSON quotes as \u0022, so assertions target that
 * escaped form to read the Alpine `variantsData` payload.
 */
function purchaseStatePage(string $slug, object $tenant): TestResponse
{
    return test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/product/'.$slug);
}

function purchaseStateProduct(array $variantOverrides = []): array
{
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create($variantOverrides);

    return [$product, $translation, $variant, $tenant];
}

function purchaseStateJson(string $html, string $key, string $value): void
{
    expect($html)->toContain('\u0022'.$key.'\u0022:\u0022'.$value.'\u0022');
}

it('marks an in-stock variant as purchasable with the right state', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct();
    app(InventoryService::class)->restock($variant, 10);

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'in_stock');
    expect($html)->toContain('\u0022purchasable\u0022:true');
});

it('marks a low-stock variant and surfaces the remaining quantity', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct();
    app(InventoryService::class)->restock($variant, 3);

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'low_stock');
    expect($html)->toContain('\u0022available_quantity\u0022:3');
    // The "Only X left" line is wired up and no longer references the old dead field.
    expect($html)->toContain("current().purchase_state === 'low_stock'");
    expect($html)->not->toContain('low_stock_remaining');
});

it('marks an out-of-stock tracked variant as not purchasable', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct();

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'out_of_stock');
    expect($html)->toContain('\u0022purchasable\u0022:false');
});

it('disables the add-to-cart CTA for discontinued variants', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct(['availability' => 'discontinued']);

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'discontinued');
    expect($html)->toContain('\u0022purchasable\u0022:false');
    expect($html)->toContain('!current().purchasable');
});

it('keeps a preorder variant purchasable and shows its expected availability', function (): void {
    $expected = now()->addDays(21);
    [$product, $translation, $variant, $tenant] = purchaseStateProduct([
        'inventory_type' => 'not_tracked',
        'fulfillment_strategy' => 'preorder',
        'expected_available_at' => $expected,
    ]);

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'preorder');
    expect($html)->toContain('\u0022purchasable\u0022:true');
    purchaseStateJson($html, 'expected_available_at', $expected->format('M j, Y'));
    // Server-rendered restock message template reaches the page.
    expect($html)->toContain("'Expected availability ' + v.expected_available_at");
});

it('keeps a dropship variant purchasable', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct([
        'fulfillment_strategy' => 'dropship',
    ]);

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'dropship');
    expect($html)->toContain('\u0022purchasable\u0022:true');
});

it('shows back-in-stock messaging for an out-of-stock variant with an expected date', function (): void {
    $expected = now()->addDays(14);
    [$product, $translation, $variant, $tenant] = purchaseStateProduct([
        'expected_available_at' => $expected,
    ]);

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'out_of_stock');
    purchaseStateJson($html, 'expected_available_at', $expected->format('M j, Y'));
    expect($html)->toContain("'Back in stock ' + v.expected_available_at");
});

it('allows a backorder policy to keep an out-of-stock variant purchasable', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct([
        'backorder_policy' => 'allow',
    ]);

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'out_of_stock');
    expect($html)->toContain('\u0022backorder_policy\u0022:\u0022allow\u0022');
    expect($html)->toContain('\u0022purchasable\u0022:true');
});

it('does not crash when a tracked variant has no stock_item row', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct();

    StockItem::query()->where('product_variant_id', $variant->id)->delete();

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    purchaseStateJson($html, 'purchase_state', 'out_of_stock');
    expect($html)->toContain('\u0022purchasable\u0022:false');
});

it('keeps CTA state consistent across desktop and sticky mobile bars', function (): void {
    [$product, $translation, $variant, $tenant] = purchaseStateProduct();

    $html = purchaseStatePage($translation->slug, $tenant)->assertOk()->getContent();

    // Both primary CTAs now gate on the derived purchasable state.
    expect($html)->toContain('x-bind:disabled="cartLoading || !current() || !current().purchasable"');
    expect($html)->toContain('x-bind:disabled="cartLoading || !current().purchasable"');
    // Neither references the old raw availability/discontinued shortcut anymore.
    expect($html)->not->toContain("current().availability === 'out_of_stock'");
});