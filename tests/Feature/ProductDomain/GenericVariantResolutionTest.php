<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use Illuminate\Testing\TestResponse;

function pdpPayload(string $html, int $index): array
{
    preg_match_all('/JSON\.parse\(\'([^\']*)\'\)/', $html, $matches);
    $payload = $matches[1][$index];

    return json_decode(str_replace('\\u0022', '"', $payload), true);
}

function pdpGet(string $slug, object $tenant): TestResponse
{
    return test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/product/'.$slug);
}

it('drives the PDP variant selector from generic variant-defining attributes', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    $size = AttributeDefinition::query()->create([
        'code' => 'size', 'label' => 'Size', 'data_type' => 'select',
        'is_filterable' => true, 'is_variant_defining' => true,
    ]);
    $small = $size->options()->create(['value' => 'S', 'label' => 'S']);
    $large = $size->options()->create(['value' => 'L', 'label' => 'L']);

    $variantS = ProductVariant::factory()->for($product)->create(['sku' => 'TSHIRT-S']);
    $variantS->attributeValues()->create([
        'product_id' => $product->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $small->id,
    ]);

    $variantL = ProductVariant::factory()->for($product)->create(['sku' => 'TSHIRT-L']);
    $variantL->attributeValues()->create([
        'product_id' => $product->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $large->id,
    ]);

    $html = pdpGet($translation->slug, $tenant)->assertOk()->getContent();

    $variants = pdpPayload($html, 0);
    $dimensions = pdpPayload($html, 1);

    $sizeValues = array_column(array_column($variants, 'dims'), 'size');
    sort($sizeValues);
    expect($sizeValues)->toBe(['L', 'S']);
    expect($dimensions)->toHaveCount(1);
    expect($dimensions[0])->toMatchArray(['code' => 'size', 'label' => 'Size', 'suffix' => '']);
});

it('keeps native phone dimensions driving the PDP selector equivalently', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    ProductVariant::factory()->for($product)->create([
        'sku' => 'PHONE-B', 'color' => 'Black', 'storage_gb' => 128, 'region' => 'Global',
    ]);
    ProductVariant::factory()->for($product)->create([
        'sku' => 'PHONE-W', 'color' => 'White', 'storage_gb' => 256, 'region' => 'Global',
    ]);

    $html = pdpGet($translation->slug, $tenant)->assertOk()->getContent();

    $variants = pdpPayload($html, 0);
    $dimensions = pdpPayload($html, 1);

    expect(array_column(array_column($variants, 'dims'), 'color'))->toBe(['Black', 'White']);
    expect(array_column(array_column($variants, 'dims'), 'storage'))->toBe(['128', '256']);
    expect(array_column($dimensions, 'code'))->toBe(['color', 'storage', 'region']);
    expect(collect($dimensions)->firstWhere('code', 'storage'))->toMatchArray(['label' => 'Storage', 'suffix' => 'GB']);
});

it('ignores product-level attribute values and applies unit suffixes on generic dimensions', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    $size = AttributeDefinition::query()->create([
        'code' => 'size', 'label' => 'Size', 'data_type' => 'select', 'unit' => 'cm',
        'is_variant_defining' => true,
    ]);
    $medium = $size->options()->create(['value' => 'M', 'label' => 'M']);

    // Product-level value on a variant-defining definition must NOT become a dimension.
    $shade = AttributeDefinition::query()->create([
        'code' => 'shade', 'label' => 'Shade', 'data_type' => 'select', 'is_variant_defining' => true,
    ]);
    $product->attributeValues()->create(['attribute_definition_id' => $shade->id, 'value_string' => 'Midnight']);

    $variantM = ProductVariant::factory()->for($product)->create(['sku' => 'TSHIRT-M']);
    $variantM->attributeValues()->create([
        'product_id' => $product->id, 'attribute_definition_id' => $size->id, 'attribute_option_id' => $medium->id,
    ]);

    $html = pdpGet($translation->slug, $tenant)->assertOk()->getContent();

    $variants = pdpPayload($html, 0);
    $dimensions = pdpPayload($html, 1);

    expect(array_column(array_column($variants, 'dims'), 'size'))->toBe(['M']);
    expect(collect($dimensions)->firstWhere('code', 'size'))->toMatchArray(['label' => 'Size', 'suffix' => 'cm']);
    expect(array_column($dimensions, 'code'))->not->toContain('shade');
});
