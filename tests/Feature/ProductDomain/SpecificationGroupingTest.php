<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use Illuminate\Testing\TestResponse;

/**
 * Holds a published product with a translation and a set of product-level
 * specification values, returning the rendered PDP HTML so group ordering,
 * attribute ordering, unit rendering and the General fallback can be asserted
 * against the server-rendered specification section.
 */
function specGet(string $slug, object $tenant): TestResponse
{
    return test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/product/'.$slug);
}

function specProduct(): array
{
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    return [$product, $translation, $tenant];
}

it('orders groups by group_sort_order, attributes by sort_order and renders units', function (): void {
    [$product, $translation, $tenant] = specProduct();

    $definitions = [
        ['code' => 'screen_size', 'label' => 'Screen Size', 'value' => '6.7', 'unit' => 'inch', 'group' => 'Display', 'group_sort_order' => 0, 'sort_order' => 1],
        ['code' => 'resolution', 'label' => 'Resolution', 'value' => '2400x1080', 'unit' => 'px', 'group' => 'Display', 'group_sort_order' => 0, 'sort_order' => 0],
        ['code' => 'weight', 'label' => 'Weight', 'value' => '199', 'unit' => 'g', 'group' => 'Physical', 'group_sort_order' => 1, 'sort_order' => 0],
        ['code' => 'warranty', 'label' => 'Warranty', 'value' => '1 year', 'unit' => null, 'group' => null, 'group_sort_order' => 2, 'sort_order' => 0],
    ];

    foreach ($definitions as $definition) {
        $attribute = AttributeDefinition::query()->create(collect($definition)->except('value')->toArray());
        $product->attributeValues()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $attribute->id,
            'value_string' => $definition['value'],
        ]);
    }

    $html = specGet($translation->slug, $tenant)->assertOk()->getContent();

    expect(strpos($html, 'Display'))->toBeLessThan(strpos($html, 'Physical'));
    expect(strpos($html, 'Physical'))->toBeLessThan(strpos($html, 'General'));

    // Ordering inside the Display group: Resolution (sort_order 0) before Screen Size (1).
    expect(strpos($html, 'Resolution'))->toBeLessThan(strpos($html, 'Screen Size'));

    // Units rendered next to the value.
    expect($html)->toMatch('/6\.7\s*<span[^>]*>inch<\/span>/');
    expect($html)->toMatch('/2400x1080\s*<span[^>]*>px<\/span>/');
    expect($html)->toMatch('/199\s*<span[^>]*>g<\/span>/');

    // Semantic definition-list markup.
    expect($html)->toContain('<dl');
    expect($html)->toContain('<dt');
    expect($html)->toContain('<dd');

    // The group-less attribute falls back under the General heading.
    expect($html)->toContain('<h3');
    expect($html)->toContain('Screen Size');
    expect($html)->toContain('Resolution');
    expect($html)->toContain('Weight');
    expect($html)->toContain('Warranty');
});

it('keeps legacy NULL-group product specifications intact under General', function (): void {
    [$product, $translation, $tenant] = specProduct();

    $definitions = [
        ['code' => 'brand_origin', 'label' => 'Origin', 'value' => 'China', 'sort_order' => 2],
        ['code' => 'release_year', 'label' => 'Release Year', 'value' => '2025', 'sort_order' => 0],
        ['code' => 'protection', 'label' => 'Protection', 'value' => 'Water resistant', 'sort_order' => 1],
    ];

    foreach ($definitions as $definition) {
        $attribute = AttributeDefinition::query()->create(collect($definition)->except('value')->toArray());
        $product->attributeValues()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $attribute->id,
            'value_string' => $definition['value'],
        ]);
    }

    $html = specGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('General');
    expect(substr_count($html, '<dt'))->toBe(3);
    expect($html)->toContain('Origin');
    expect($html)->toContain('Release Year');
    expect($html)->toContain('Water resistant');

    // Ordered by sort_order: Release Year (0) before Protection (1) before Origin (2).
    expect(strpos($html, 'Release Year'))->toBeLessThan(strpos($html, 'Protection'));
    expect(strpos($html, 'Protection'))->toBeLessThan(strpos($html, 'Origin'));
});

it('keeps variant-scoped attribute values out of the product specification list', function (): void {
    [$product, $translation, $tenant] = specProduct();

    $spec = AttributeDefinition::query()->create([
        'code' => 'finish', 'label' => 'Finish', 'data_type' => 'select',
        'is_variant_defining' => false,
    ]);
    $product->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $spec->id,
        'value_string' => 'Glossy',
    ]);

    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'V-1']);
    $variant->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $spec->id,
        'value_string' => 'Matte',
    ]);

    $html = specGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('Glossy');
    expect($html)->not->toContain('Matte');
});

it('renders an empty state when no product-level specifications exist', function (): void {
    [$product, $translation, $tenant] = specProduct();

    $html = specGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('No specifications listed yet.');
});
