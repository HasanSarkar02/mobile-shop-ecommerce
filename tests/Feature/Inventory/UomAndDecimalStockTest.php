<?php

declare(strict_types=1);

use App\Enums\StockAdjustmentReason;
use App\Enums\StockStatus;
use App\Enums\UomType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Services\IndustrySeeders\IndustrySeederService;
use App\Services\InventoryService;

beforeEach(function () {
    actingAsTenant();
});

function uom(string $code, string $name, string $type): UnitOfMeasure
{
    return UnitOfMeasure::query()->create([
        'code' => $code,
        'name' => $name,
        'type' => $type,
    ]);
}

it('assigns a unit of measure to a product', function (): void {
    $kg = uom('kg', 'Kilogram', 'weight');
    $product = Product::factory()->create(['status' => 'published', 'uom_id' => $kg->id]);

    expect($product->uom)->not->toBeNull()
        ->and($product->uom->code)->toBe('kg')
        ->and($product->uom->type)->toBe(UomType::Weight)
        ->and($product->uom->isDiscrete())->toBeFalse();
});

it('seeds starter uoms for grocery tenants idempotently', function (): void {
    $tenant = actingAsTenant(['industry' => 'grocery']);

    app(IndustrySeederService::class)->seed($tenant);
    app(IndustrySeederService::class)->seed($tenant);

    $codes = UnitOfMeasure::query()->orderBy('code')->pluck('code')->all();

    expect($codes)->toBe(['dozen', 'g', 'kg', 'l', 'ml', 'pack', 'pcs'])
        ->and(UnitOfMeasure::query()->count())->toBe(7)
        ->and(UnitOfMeasure::query()->where('type', 'weight')->count())->toBe(2)
        ->and(UnitOfMeasure::query()->where('type', 'volume')->count())->toBe(2);
});

it('saves and deducts decimal quantities exactly', function (): void {
    $service = app(InventoryService::class);
    $variant = createTestVariant();

    // String input — canonical path.
    $service->restock($variant, '5.000');
    $service->adjust($variant, '-1.250', StockAdjustmentReason::Damaged);

    expect($variant->stockItems()->first()->fresh()->quantity)->toBe('3.750')
        ->and($service->availableDecimal($variant))->toBe('3.750');
});

it('reserves decimal quantities without losing precision', function (): void {
    $service = app(InventoryService::class);
    $variant = createTestVariant();

    $service->restock($variant, 5);
    $service->reserve($variant, '1.250');

    $stockItem = $variant->stockItems()->first()->fresh();
    expect($stockItem->reserved_quantity)->toBe('1.250')
        ->and($stockItem->availableQuantityDecimal())->toBe('3.750');

    $service->commit($variant, '1.250');

    $fresh = $variant->stockItems()->first()->fresh();
    expect($fresh->quantity)->toBe('3.750')
        ->and($fresh->reserved_quantity)->toBe('0.000')
        ->and($service->availableDecimal($variant))->toBe('3.750');
});

it('survives floating-point edge cases via bcmath', function (): void {
    $service = app(InventoryService::class);
    $variant = createTestVariant();

    // The classic float trap: 0.1 + 0.2 !== 0.3. Four successive 1.250
    // deductions from 5.000 must land on EXACTLY zero, never a residue.
    $service->restock($variant, 5);

    foreach ([0, 1, 2, 3] as $i) {
        $service->commit($variant, '1.250');
    }

    expect($service->availableDecimal($variant))->toBe('0.000')
        ->and(bccomp($service->availableDecimal($variant), '0.000', 3))->toBe(0);

    // Fractional accumulation: 0.1 + 0.2 must equal 0.3 exactly at scale 3.
    $loose = createTestVariant(['sku' => 'LOOSE-RICE-'.uniqid()]);
    $service->restock($loose, '0.100');
    $service->restock($loose, '0.200');

    expect(bccomp($service->availableDecimal($loose), '0.300', 3))->toBe(0);

    // And a 0.300 deduction lands on exact zero — OutOfStock by bccomp,
    // not by a float comparison.
    $service->commit($loose, '0.300');

    expect($service->availableDecimal($loose))->toBe('0.000')
        ->and($service->stockStatus($loose))->toBe(StockStatus::OutOfStock);
});

it('keeps discrete whole-unit goods on the integer fast path', function (): void {
    $service = app(InventoryService::class);
    $variant = createTestVariant();

    $service->restock($variant, 10);
    $service->reserve($variant, 3);
    $service->commit($variant, 3);

    $stockItem = $variant->stockItems()->first()->fresh();

    // Integer surface unchanged for discrete goods...
    expect($service->availableQuantity($variant))->toBe(7)
        ->and($stockItem->quantity)->toBe('7.000')
        // ...while the canonical decimal form stays consistent ("7.000").
        ->and($service->availableDecimal($variant))->toBe('7.000')
        ->and(bccomp($service->availableDecimal($variant), '7.000', 3))->toBe(0);

    // Movement ledger records fractional-capable values even for ints.
    $movement = StockMovement::query()
        ->where('product_variant_id', $variant->id)
        ->where('type', 'sale')
        ->latest('id')
        ->first();
    expect($movement->quantity_change)->toBe('-3.000');
});
