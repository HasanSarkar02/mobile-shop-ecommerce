<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\BulkVariantGeneratorService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Validation\ValidationException;

/**
 * Helper: a variant-defining select attribute with the given option labels.
 *
 * @param  list<string>  $labels
 */
function generatorDefinition(string $code, string $label, array $optionLabels): AttributeDefinition
{
    $definition = AttributeDefinition::query()->create([
        'code' => $code,
        'label' => $label,
        'data_type' => 'select',
        'is_filterable' => true,
        'is_variant_defining' => true,
    ]);

    foreach ($optionLabels as $value) {
        $definition->options()->create(['value' => $value, 'label' => $value]);
    }

    return $definition->fresh();
}

/**
 * @return array<int|string, list<int>>
 */
function selectionFor(AttributeDefinition ...$definitions): array
{
    $selections = [];

    foreach ($definitions as $definition) {
        $selections[$definition->id] = $definition->options->pluck('id')->all();
    }

    return $selections;
}

it('generates the full cartesian product with unique signatures and EAV-only writes', function (): void {
    actingAsTenant();

    $product = Product::factory()->create(['status' => 'published', 'model_number' => 'TEE', 'base_price' => 150000]);

    $color = generatorDefinition('fabric_color', 'Color', ['White', 'Black']);
    $size = generatorDefinition('size', 'Size', ['M', 'L', 'XL']);

    $result = app(BulkVariantGeneratorService::class)->generate(
        $product,
        selectionFor($color, $size),
        basePrice: 150000,
    );

    expect($result)->toBe(['created' => 6, 'skipped' => 0]);
    expect($product->variants()->count())->toBe(6);

    $signatures = [];
    $product->variants()->with(['attributeValues.attributeDefinition', 'attributeValues.attributeOption'])->get()
        ->each(function ($variant) use (&$signatures): void {
            // EAV rows only — native phone columns must stay untouched (#48)
            expect($variant->color)->toBeNull();
            expect($variant->storage_gb)->toBeNull();
            expect($variant->ram_gb)->toBeNull();
            expect($variant->attributeValues)->toHaveCount(2);
            expect($variant->price)->toBe(150000);

            $signature = $variant->signature();
            expect($signature)->not->toBeNull();
            $signatures[] = (string) $signature;
        });

    expect(count(array_unique($signatures)))->toBe(6);

    // SKU pattern: prefix + option labels
    expect($product->variants()->where('sku', 'TEE-WHITE-M')->exists())->toBeTrue();
    expect($product->variants()->where('sku', 'TEE-BLACK-XL')->exists())->toBeTrue();
});

it('is idempotent — a second run skips every existing combination', function (): void {
    actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $size = generatorDefinition('size', 'Size', ['S', 'M']);

    $service = app(BulkVariantGeneratorService::class);
    $selections = selectionFor($size);

    $first = $service->generate($product, $selections, basePrice: 99000);
    $second = $service->generate($product, $selections, basePrice: 99000);

    expect($first)->toBe(['created' => 2, 'skipped' => 0]);
    expect($second)->toBe(['created' => 0, 'skipped' => 2]);
    expect($product->variants()->count())->toBe(2);
});

it('keeps generated variants tenant-isolated', function (): void {
    $tenantA = actingAsTenant(['subdomain' => 'gen-a']);
    $productA = Product::factory()->create(['status' => 'published']);
    $sizeA = generatorDefinition('size-a', 'Size', ['S', 'M']);

    app(BulkVariantGeneratorService::class)->generate(
        $productA,
        selectionFor($sizeA),
        basePrice: 50000,
    );

    $tenantB = Tenant::factory()->create(['subdomain' => 'gen-b']);
    app(Tenancy::class)->set($tenantB);

    $productB = Product::factory()->create(['status' => 'published']);
    $sizeB = generatorDefinition('size-b', 'Size', ['S', 'M']);

    $resultB = app(BulkVariantGeneratorService::class)->generate(
        $productB,
        selectionFor($sizeB),
        basePrice: 50000,
    );

    // Tenant B sees only its own definitions/options and its own variants
    expect($resultB)->toBe(['created' => 2, 'skipped' => 0]);
    expect($productB->variants()->count())->toBe(2);
    expect(app(BulkVariantGeneratorService::class)::MAX_COMBINATIONS)->toBe(36);

    app(Tenancy::class)->set($tenantA);
    expect($productA->variants()->count())->toBe(2);
});

it('rejects generation when no options are selected or price is not positive', function (): void {
    actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);

    $service = app(BulkVariantGeneratorService::class);

    expect(fn () => $service->generate($product, [], basePrice: 10000))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->generate($product, ['999' => [1, 2]], basePrice: 10000))
        ->toThrow(ValidationException::class);

    $size = generatorDefinition('size-zero-price', 'Size', ['S']);
    expect(fn () => $service->generate($product, selectionFor($size), basePrice: 0))
        ->toThrow(ValidationException::class);

    expect($product->variants()->count())->toBe(0);
});

it('honours a custom SKU prefix and suffixes on collision', function (): void {
    actingAsTenant();

    $product = Product::factory()->create(['status' => 'published', 'model_number' => 'TEE']);
    $size = generatorDefinition('sku-size', 'Size', ['S']);

    $service = app(BulkVariantGeneratorService::class);

    $first = $service->generate($product, selectionFor($size), basePrice: 80000, baseSku: 'BATCH7');
    expect($first['created'])->toBe(1);
    expect($product->variants()->where('sku', 'BATCH7-S')->exists())->toBeTrue();

    // Same prefix + same combination via a fresh product would collide on SKU;
    // within one product the combination is skipped by signature. Simulate a
    // SKU collision instead by pre-creating a variant that owns BATCH7-S's
    // successor path: delete the EAV row so signature no longer matches but
    // a second generate run re-creates the combo under a suffixed SKU.
    $variant = $product->variants()->firstOrFail();
    $variant->attributeValues()->delete();

    $second = $service->generate($product, selectionFor($size), basePrice: 80000, baseSku: 'BATCH7');
    expect($second['created'])->toBe(1);
    expect($product->variants()->where('sku', 'BATCH7-S-2')->exists())->toBeTrue();
});
