<?php

declare(strict_types=1);

use App\Models\StockItem;
use App\Services\Inventory\StockValuationService;
use App\Services\InventoryService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

it('multiplies decimal quantity by cost via bcmath', function (): void {
    $service = app(StockValuationService::class);

    expect($service->multiplyQuantityCost('1.500', 12000))->toBe(18000)
        ->and($service->multiplyQuantityCost('2.000', 5000))->toBe(10000)
        ->and($service->multiplyQuantityCost('0.333', 333))->toBe(111);
});

it('returns null valueOnHand when variant cost is null', function (): void {
    $variant = createTestVariant(['cost_price' => null]);
    app(InventoryService::class)->restock($variant, '5.000');

    $stockItem = StockItem::query()->where('product_variant_id', $variant->id)->firstOrFail();
    $service = app(StockValuationService::class);

    expect($service->valueOnHand($stockItem))->toBeNull();
});

it('keeps totalOnHandValue isolated per tenant', function (): void {
    $tenantA = tenant();
    $variantA = createTestVariant(['cost_price' => 10000]);
    app(InventoryService::class)->restock($variantA, '2.500');

    $service = app(StockValuationService::class);
    expect($service->totalOnHandValue())->toBe(25000);

    $tenantB = actingAsTenant(['subdomain' => 'tenant-b-'.uniqid()]);
    $variantB = createTestVariant(['cost_price' => 20000]);
    app(InventoryService::class)->restock($variantB, '1.000');

    // Tenant B sees only its own stock
    expect($service->totalOnHandValue())->toBe(20000);

    // Switch back to A — isolation holds
    app(Tenancy::class)->set($tenantA);
    expect($service->totalOnHandValue())->toBe(25000);

    // Restore context for afterEach
    app(Tenancy::class)->set($tenantB);
});

it('counts unvalued stock rows separately', function (): void {
    $valued = createTestVariant(['cost_price' => 5000]);
    app(InventoryService::class)->restock($valued, '3.000');

    $unvalued = createTestVariant(['cost_price' => null]);
    app(InventoryService::class)->restock($unvalued, '2.000');

    $service = app(StockValuationService::class);

    expect($service->unvaluedCount())->toBe(1)
        ->and($service->totalOnHandValue())->toBe(15000);
});

it('DB total matches bcmath loop for multiple stock rows', function (): void {
    $variantA = createTestVariant(['cost_price' => 10000]);
    app(InventoryService::class)->restock($variantA, '1.500');
    $variantB = createTestVariant(['cost_price' => 20000]);
    app(InventoryService::class)->restock($variantB, '2.000');
    $variantC = createTestVariant(['cost_price' => 5000]);
    app(InventoryService::class)->restock($variantC, '0.500');

    $service = app(StockValuationService::class);
    $dbTotal = $service->totalOnHandValue();

    $expected = $service->multiplyQuantityCost('1.500', 10000)
        + $service->multiplyQuantityCost('2.000', 20000)
        + $service->multiplyQuantityCost('0.500', 5000);

    expect($dbTotal)->toBe($expected);
});
