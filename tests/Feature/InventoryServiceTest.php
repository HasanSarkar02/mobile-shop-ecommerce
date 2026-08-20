<?php

use App\Enums\StockAdjustmentReason;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    actingAsTenant();
});

it('has zero available stock for a freshly created variant', function () {
    $variant = createTestVariant();

    expect(app(InventoryService::class)->availableQuantity($variant))->toBe(0);
});

it('increases available stock after restock', function () {
    $variant = createTestVariant();
    app(InventoryService::class)->restock($variant, 20, null, 'Initial stock');

    expect(app(InventoryService::class)->availableQuantity($variant))->toBe(20);
    expect($variant->stockItems()->first()->quantity)->toBe(20);
});

it('reserves stock without touching real quantity', function () {
    $variant = createTestVariant();
    $service = app(InventoryService::class);
    $service->restock($variant, 10);

    $service->reserve($variant, 4);

    $stockItem = $variant->stockItems()->first()->fresh();
    expect($stockItem->quantity)->toBe(10);
    expect($stockItem->reserved_quantity)->toBe(4);
    expect($service->availableQuantity($variant))->toBe(6);
});

it('throws when reserving more than available and backorder is denied', function () {
    $variant = createTestVariant(['backorder_policy' => 'deny']);
    $service = app(InventoryService::class);
    $service->restock($variant, 2);

    $service->reserve($variant, 5);
})->throws(InsufficientStockException::class);

it('allows reservation past zero when backorder is allowed', function () {
    $variant = createTestVariant(['backorder_policy' => 'allow']);
    $service = app(InventoryService::class);
    $service->restock($variant, 1);

    $service->reserve($variant, 5);

    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(5);
});

it('commits a reservation by decrementing real and reserved quantity together', function () {
    $variant = createTestVariant();
    $service = app(InventoryService::class);
    $service->restock($variant, 10);
    $service->reserve($variant, 3);

    $service->commit($variant, 3);

    $stockItem = $variant->stockItems()->first()->fresh();
    expect($stockItem->quantity)->toBe(7);
    expect($stockItem->reserved_quantity)->toBe(0);
});

it('releases a reservation back to available stock', function () {
    $variant = createTestVariant();
    $service = app(InventoryService::class);
    $service->restock($variant, 10);
    $service->reserve($variant, 4);

    $service->release($variant, 4);

    expect($service->availableQuantity($variant))->toBe(10);
});

it('logs an adjustment movement with its reason and comment', function () {
    $variant = createTestVariant();
    $service = app(InventoryService::class);
    $service->restock($variant, 10);

    $service->adjust($variant, -2, StockAdjustmentReason::Damaged, null, 'Dropped during unboxing');

    expect($variant->stockItems()->first()->fresh()->quantity)->toBe(8);

    $movement = StockMovement::query()->where('product_variant_id', $variant->id)->latest('id')->first();
    expect($movement->reason->value)->toBe('damaged');
    expect($movement->comment)->toBe('Dropped during unboxing');
});

it('commits serialized stock by marking specific serials sold', function () {
    $variant = createTestVariant(['inventory_type' => 'serialized']);
    SerialNumber::factory()->count(3)->for($variant, 'variant')->create(['status' => 'available']);

    $order = Order::factory()->create(['status' => 'pending']);
    $item = $order->items()->create([
        'tenant_id' => $order->tenant_id,
        'product_variant_id' => $variant->id,
        'product_name_snapshot' => 'Test',
        'variant_sku_snapshot' => $variant->sku,
        'unit_price' => $variant->price,
        'quantity' => 2,
        'line_total' => $variant->price * 2,
    ]);

    app(InventoryService::class)->commit($variant, 2, null, null, $item);

    expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'sold')->count())->toBe(2);
    expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'sold')->where('order_item_id', $item->id)->count())->toBe(2);
    expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'available')->count())->toBe(1);
});

it('throws when committing more serials than are available', function () {
    $variant = createTestVariant(['inventory_type' => 'serialized']);
    SerialNumber::factory()->count(1)->for($variant, 'variant')->create(['status' => 'available']);

    $order = Order::factory()->create(['status' => 'pending']);
    $item = $order->items()->create([
        'tenant_id' => $order->tenant_id,
        'product_variant_id' => $variant->id,
        'product_name_snapshot' => 'Test',
        'variant_sku_snapshot' => $variant->sku,
        'unit_price' => $variant->price,
        'quantity' => 2,
        'line_total' => $variant->price * 2,
    ]);

    app(InventoryService::class)->commit($variant, 2, null, null, $item);
})->throws(InsufficientStockException::class);

it('requires an order item to commit serialized stock', function () {
    $variant = createTestVariant(['inventory_type' => 'serialized']);
    SerialNumber::factory()->count(1)->for($variant, 'variant')->create(['status' => 'available']);

    app(InventoryService::class)->commit($variant, 1);
})->throws(InvalidArgumentException::class);

it('does not treat stock held by an expired pending order as available before release', function () {
    $variant = createTestVariant();
    $service = app(InventoryService::class);
    $service->restock($variant, 1);
    $service->reserve($variant, 1);

    expect($service->availableQuantity($variant))->toBe(0);

    $order = Order::factory()->create(['status' => 'pending', 'reservation_expires_at' => now()->subHour()]);
    $order->items()->create([
        'tenant_id' => $order->tenant_id,
        'product_variant_id' => $variant->id,
        'product_name_snapshot' => 'Test',
        'variant_sku_snapshot' => $variant->sku,
        'unit_price' => $variant->price,
        'quantity' => 1,
        'line_total' => $variant->price,
    ]);

    expect($service->availableQuantity($variant))->toBe(0);
    expect($service->resolvePurchaseStates(collect([$variant]))->get($variant->id)['available_quantity'])->toBe(0);
    expect(fn () => $service->reserve($variant, 1))->toThrow(InsufficientStockException::class);
});

it('reports active reservations as unavailable and rejects another reservation', function () {
    $variant = createTestVariant();
    $service = app(InventoryService::class);
    $service->restock($variant, 1);
    $service->reserve($variant, 1);

    expect($service->availableQuantity($variant))->toBe(0);
    expect($service->resolvePurchaseStates(collect([$variant]))->get($variant->id)['available_quantity'])->toBe(0);
    expect(fn () => $service->reserve($variant, 1))->toThrow(InsufficientStockException::class);
});
