<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\FacetResolver;

/**
 * Builds facet options from variant-scoped attributes. Each option's count is
 * the number of DISTINCT products carrying it — three S-sized variants on one
 * product must still count as one product.
 */
it('builds facets from variant-scoped attributes with distinct product counts', function (): void {
    actingAsTenant();

    $size = AttributeDefinition::query()->create(['code' => 'size', 'label' => 'Size', 'data_type' => 'select', 'is_filterable' => true, 'is_variant_defining' => true]);
    $optionS = $size->options()->create(['value' => 'S', 'label' => 'S']);
    $optionL = $size->options()->create(['value' => 'L', 'label' => 'L']);

    $color = AttributeDefinition::query()->create(['code' => 'color', 'label' => 'Color', 'data_type' => 'select', 'is_filterable' => true, 'is_variant_defining' => true]);
    $optionRed = $color->options()->create(['value' => 'red', 'label' => 'Red']);
    $optionBlue = $color->options()->create(['value' => 'blue', 'label' => 'Blue']);
    $optionGreen = $color->options()->create(['value' => 'green', 'label' => 'Green']);

    $productA = Product::factory()->create(['status' => 'published']);

    // Three distinct S-sized variants on A, one per color.
    $pairs = [['TSHIRT-A-RED', $optionRed], ['TSHIRT-A-BLUE', $optionBlue], ['TSHIRT-A-GREEN', $optionGreen]];

    foreach ($pairs as [$sku, $colorOption]) {
        $variant = ProductVariant::factory()->for($productA)->create(['sku' => $sku]);
        $variant->attributeValues()->create(['product_id' => $productA->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $optionS->id]);
        $variant->attributeValues()->create(['product_id' => $productA->id, 'attribute_definition_id' => $color->id, 'attribute_option_id' => $colorOption->id]);
    }

    $productB = Product::factory()->create(['status' => 'published']);
    $variantB1 = ProductVariant::factory()->for($productB)->create(['sku' => 'TSHIRT-B-S-RED']);
    $variantB1->attributeValues()->create(['product_id' => $productB->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $optionS->id]);
    $variantB1->attributeValues()->create(['product_id' => $productB->id, 'attribute_definition_id' => $color->id, 'attribute_option_id' => $optionRed->id]);
    $variantB2 = ProductVariant::factory()->for($productB)->create(['sku' => 'TSHIRT-B-L-RED']);
    $variantB2->attributeValues()->create(['product_id' => $productB->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $optionL->id]);
    $variantB2->attributeValues()->create(['product_id' => $productB->id, 'attribute_definition_id' => $color->id, 'attribute_option_id' => $optionRed->id]);

    // Product-level attribute facets still work.
    $fabric = AttributeDefinition::query()->create(['code' => 'fabric', 'label' => 'Fabric', 'data_type' => 'select', 'is_filterable' => true]);
    $cotton = $fabric->options()->create(['value' => 'cotton', 'label' => 'Cotton']);
    $productA->attributeValues()->create(['attribute_definition_id' => $fabric->id, 'attribute_option_id' => $cotton->id]);

    $facets = app(FacetResolver::class)->resolve(Product::query()->published());

    $sizeOptions = collect($facets['attributes']['size']['options'] ?? [])->keyBy('value');
    expect($sizeOptions['S']['count'])->toBe(2);
    expect($sizeOptions['L']['count'])->toBe(1);

    $colorOptions = collect($facets['attributes']['color']['options'] ?? [])->keyBy('value');
    expect($colorOptions['Red']['count'])->toBe(2);
    expect($colorOptions['Blue']['count'])->toBe(1);
    expect($colorOptions['Green']['count'])->toBe(1);

    $fabricOptions = collect($facets['attributes']['fabric']['options'] ?? [])->keyBy('value');
    expect($fabricOptions['Cotton']['count'])->toBe(1);
});

it('excludes variant-scoped facets for unpublished products', function (): void {
    actingAsTenant();

    $size = AttributeDefinition::query()->create(['code' => 'size', 'label' => 'Size', 'data_type' => 'select', 'is_filterable' => true, 'is_variant_defining' => true]);
    $optionS = $size->options()->create(['value' => 'S', 'label' => 'S']);

    $draft = Product::factory()->create(['status' => 'draft']);
    $draftVariant = ProductVariant::factory()->for($draft)->create(['sku' => 'DRAFT-S']);
    $draftVariant->attributeValues()->create(['product_id' => $draft->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $optionS->id]);

    $published = Product::factory()->create(['status' => 'published']);
    $pubVariant = ProductVariant::factory()->for($published)->create(['sku' => 'PUB-S']);
    $pubVariant->attributeValues()->create(['product_id' => $published->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $optionS->id]);

    $facets = app(FacetResolver::class)->resolve(Product::query()->published());

    expect(collect($facets['attributes']['size']['options'] ?? [])->keyBy('value')['S']['count'])->toBe(1);
});
