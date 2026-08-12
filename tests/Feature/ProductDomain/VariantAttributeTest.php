<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;

it('supports variant-scoped and product-level attribute values independently', function (): void {
    actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    $variantSm = ProductVariant::factory()->for($product)->create(['sku' => 'TSHIRT-S-M']);
    $variantLg = ProductVariant::factory()->for($product)->create(['sku' => 'TSHIRT-S-L']);

    $size = AttributeDefinition::query()->create([
        'code' => 'size', 'label' => 'Size', 'data_type' => 'select',
        'is_filterable' => true, 'is_variant_defining' => true,
    ]);
    $material = AttributeDefinition::query()->create([
        'code' => 'material', 'label' => 'Material', 'data_type' => 'select',
    ]);
    $small = $size->options()->create(['value' => 'S', 'label' => 'S']);
    $large = $size->options()->create(['value' => 'L', 'label' => 'L']);

    // Product-level attribute (specification, no variant).
    $product->attributeValues()->create([
        'attribute_definition_id' => $material->id,
        'value_string' => 'Cotton',
    ]);

    // Variant-scoped attributes: each variant carries its own dimension tuple.
    $variantSm->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $size->id,
        'attribute_option_id' => $small->id,
    ]);
    $variantLg->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $size->id,
        'attribute_option_id' => $large->id,
    ]);

    // The relation manager writes rows keyed off product_id + product_variant_id.
    expect($product->attributeValues)->toHaveCount(3);
    expect($product->attributeValues->whereNull('product_variant_id'))->toHaveCount(1);
    expect($product->attributeValues->whereNotNull('product_variant_id'))->toHaveCount(2);

    expect($variantSm->attributeValues)->toHaveCount(1);
    expect($variantSm->attributeValues->first()->displayValue())->toBe('S');
    expect($variantLg->attributeValues->first()->displayValue())->toBe('L');

    expect($variantSm->attributeValues->first()->variant->is($variantSm))->toBeTrue();
    expect($variantSm->attributeValues->first()->product->is($product))->toBeTrue();

    // Variant-scoped rows are tenant-isolated like every other catalog record.
    expect(ProductAttributeValue::query()->whereNotNull('product_variant_id')->count())->toBe(2);
});
