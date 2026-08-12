<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

function sizeDefinition(): AttributeDefinition
{
    $definition = AttributeDefinition::query()->create([
        'code' => 'size', 'label' => 'Size', 'data_type' => 'select',
        'is_filterable' => true, 'is_variant_defining' => true,
    ]);

    foreach (['S' => 'S', 'M' => 'M', 'L' => 'L'] as $value => $label) {
        $definition->options()->create(['value' => $value, 'label' => $label]);
    }

    return $definition->fresh();
}

function makeVariant(Product $product, string $sku): ProductVariant
{
    return ProductVariant::factory()->for($product)->create(['sku' => $sku]);
}

it('rejects a second value of the same variant-defining attribute on one variant', function (): void {
    actingAsTenant();
    $product = Product::factory()->create(['status' => 'published']);
    $variant = makeVariant($product, 'TSHIRT-S');

    $size = sizeDefinition();
    $variant->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $size->id,
        'attribute_option_id' => $size->options->firstWhere('value', 'S')->id,
    ]);

    expect(fn () => $variant->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $size->id,
        'attribute_option_id' => $size->options->firstWhere('value', 'L')->id,
    ]))->toThrow(ValidationException::class);
});

it('rejects a duplicate combination across variants of the same product', function (): void {
    actingAsTenant();
    $product = Product::factory()->create(['status' => 'published']);
    $variantA = makeVariant($product, 'TSHIRT-S-RED');
    $variantB = makeVariant($product, 'TSHIRT-S-RED-2');

    $size = sizeDefinition();
    $sizeOption = $size->options->firstWhere('value', 'S');

    $variantA->attributeValues()->create(['product_id' => $product->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $sizeOption->id]);

    expect(fn () => $variantB->attributeValues()->create(['product_id' => $product->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $sizeOption->id]))
        ->toThrow(ValidationException::class);
});

it('allows distinct combinations (Size S vs Size L)', function (): void {
    actingAsTenant();
    $product = Product::factory()->create(['status' => 'published']);
    $variantSm = makeVariant($product, 'TSHIRT-S');
    $variantLg = makeVariant($product, 'TSHIRT-L');

    $size = sizeDefinition();
    $variantSm->attributeValues()->create(['product_id' => $product->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $size->options->firstWhere('value', 'S')->id]);
    $variantLg->attributeValues()->create(['product_id' => $product->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $size->options->firstWhere('value', 'L')->id]);

    expect($variantSm->attributeValues()->count())->toBe(1);
    expect($variantLg->attributeValues()->count())->toBe(1);
});

it('does not enforce combination rules for non-defining attributes', function (): void {
    actingAsTenant();
    $product = Product::factory()->create(['status' => 'published']);
    $variantA = makeVariant($product, 'SHIRT-A');
    $variantB = makeVariant($product, 'SHIRT-B');

    $material = AttributeDefinition::query()->create(['code' => 'material', 'label' => 'Material', 'data_type' => 'select', 'is_variant_defining' => false]);
    $cotton = $material->options()->create(['value' => 'cotton', 'label' => 'Cotton']);

    $variantA->attributeValues()->create(['product_id' => $product->id, 'attribute_definition_id' => $material->id, 'attribute_option_id' => $cotton->id]);
    $variantB->attributeValues()->create(['product_id' => $product->id, 'attribute_definition_id' => $material->id, 'attribute_option_id' => $cotton->id]);

    expect($variantA->attributeValues()->count())->toBe(1);
    expect($variantB->attributeValues()->count())->toBe(1);
});

it('keeps existing phone-style variants working unchanged', function (): void {
    actingAsTenant();
    $product = Product::factory()->create(['status' => 'published']);

    // Phone variants use the native columns only — no variant-defining
    // attribute rows, so the combination guard must not interfere.
    makeVariant($product, 'PHONE-BLACK-128')->update(['color' => 'Black', 'storage_gb' => 128, 'price' => 3500000]);
    makeVariant($product, 'PHONE-BLACK-256')->update(['color' => 'Black', 'storage_gb' => 256, 'price' => 4000000]);

    expect($product->variants()->count())->toBe(2);
    expect($product->variants->first()->signature())->toBeNull();
});
