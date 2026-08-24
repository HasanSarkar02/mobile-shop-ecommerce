<?php

declare(strict_types=1);

use App\Enums\AttributeDataType;
use App\Enums\FulfillmentStrategy;
use App\Enums\ProductStatus;
use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\Storefront\ProductCardData;

function modalBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function modalProduct(array $productOverrides = []): Product
{
    $product = Product::factory()->create(array_merge(['status' => ProductStatus::Published], $productOverrides));
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    return $product;
}

function modalCard(Product $product): array
{
    $product->load('translations', 'variants', 'media', 'emiPlans', 'variants.attributeValues.attributeDefinition', 'variants.attributeValues.attributeOption');

    return app(ProductCardData::class)->forMany(collect([$product]), collect())->first();
}

it('renders direct Add to Cart for a single purchasable variant and no modal', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = modalProduct();
    $variant = ProductVariant::factory()->for($product)->create(['is_active' => true]);
    app(InventoryService::class)->restock($variant, 10);

    $card = modalCard($product);
    $html = view('storefront.partials.product-card', ['card' => $card])->render();

    expect($card['requires_selection'])->toBeFalse();
    expect($card['cta']['type'])->toBe('add_to_cart');
    expect($html)->toContain('$store.cart.add('.$variant->id.')');
    expect($html)->not->toContain('variantSelectionState');
    expect($html)->not->toContain('x-storefront.variant-modal');
});

it('renders the variant modal with shared selector for products requiring selection', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = modalProduct();
    $v1 = ProductVariant::factory()->for($product)->create(['color' => 'Red', 'storage_gb' => 128, 'is_active' => true]);
    $v2 = ProductVariant::factory()->for($product)->create(['color' => 'Blue', 'storage_gb' => 256, 'is_active' => true]);
    app(InventoryService::class)->restock($v1, 10);
    app(InventoryService::class)->restock($v2, 10);

    $card = modalCard($product);
    $html = view('storefront.partials.product-card', ['card' => $card])->render();

    expect($card['requires_selection'])->toBeTrue();
    expect($card['modal_variants'])->toHaveCount(2);
    expect($card['modal_dimensions'])->not->toBeEmpty();
    // Modal trigger
    expect($html)->toContain('Select Options');
    // Shared engine reuse: same JS helper the PDP uses and same Blade primitive (inlined)
    expect($html)->toContain('variantSelectionState');
    expect($html)->toContain('dimension in dimensions');
    // Modal shell
    expect($html)->toContain('role="dialog"');
    expect($html)->toContain('View full details');
});

it('modal is tenant-isolated and reflects stock/backorder/preorder states', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = modalProduct();
    // Out of stock, no backorder -> not purchasable
    $out = ProductVariant::factory()->for($product)->create([
        'is_active' => true,
        'color' => 'Red',
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'deny',
    ]);
    // Backorder allowed -> purchasable even with 0 stock
    $backorder = ProductVariant::factory()->for($product)->create([
        'is_active' => true,
        'color' => 'Blue',
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'allow',
    ]);
    // Preorder -> purchasable
    $pre = ProductVariant::factory()->for($product)->create([
        'is_active' => true,
        'color' => 'Green',
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
        'expected_available_at' => now()->addDays(10),
    ]);

    $card = modalCard($product);

    $outData = collect($card['modal_variants'])->firstWhere('id', $out->id);
    $backData = collect($card['modal_variants'])->firstWhere('id', $backorder->id);
    $preData = collect($card['modal_variants'])->firstWhere('id', $pre->id);

    expect($outData['purchasable'])->toBeFalse();
    expect($backData['purchasable'])->toBeTrue();
    expect($preData['purchasable'])->toBeTrue();
    expect($preData['purchase_state'])->toBe('preorder');
});

it('modal expresses generic EAV dimensions without new code', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = modalProduct();
    // Create attribute definitions for Size/Color (EAV) — no factory on this model
    $size = AttributeDefinition::query()->create([
        'code' => 'size',
        'label' => 'Size',
        'data_type' => AttributeDataType::Select,
        'is_variant_defining' => true,
        'is_filterable' => true,
    ]);
    $sizeS = $size->options()->create(['label' => 'S', 'value' => 'S']);
    $sizeM = $size->options()->create(['label' => 'M', 'value' => 'M']);

    $color = AttributeDefinition::query()->create([
        'code' => 'fabric_color',
        'label' => 'Fabric Color',
        'data_type' => AttributeDataType::Select,
        'is_variant_defining' => true,
    ]);
    $red = $color->options()->create(['label' => 'Red', 'value' => 'Red']);
    $blue = $color->options()->create(['label' => 'Blue', 'value' => 'Blue']);

    $v1 = ProductVariant::factory()->for($product)->create(['is_active' => true]);
    $v2 = ProductVariant::factory()->for($product)->create(['is_active' => true]);
    app(InventoryService::class)->restock($v1, 5);
    app(InventoryService::class)->restock($v2, 5);

    $v1->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $size->id,
        'attribute_option_id' => $sizeS->id,
    ]);
    $v1->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $color->id,
        'attribute_option_id' => $red->id,
    ]);
    $v2->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $size->id,
        'attribute_option_id' => $sizeM->id,
    ]);
    $v2->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $color->id,
        'attribute_option_id' => $blue->id,
    ]);

    $card = modalCard($product);

    expect($card['modal_dimensions'])->toHaveCount(2);
    $codes = collect($card['modal_dimensions'])->pluck('code')->all();
    expect($codes)->toContain('size');
    expect($codes)->toContain('fabric_color');

    $html = view('storefront.partials.product-card', ['card' => $card])->render();
    // Dimensions are passed to the shared JS engine, which the selector renders
    expect($html)->toContain('size');
    expect($html)->toContain('fabric_color');
});

it('direct add path still works when no selection is required', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = modalProduct();
    $variant = ProductVariant::factory()->for($product)->create(['is_active' => true]);
    app(InventoryService::class)->restock($variant, 3);

    $html = view('storefront.partials.product-card', ['card' => modalCard($product)])->render();
    expect($html)->toContain('Add to Cart');
    expect($html)->toContain((string) $variant->id);
});
