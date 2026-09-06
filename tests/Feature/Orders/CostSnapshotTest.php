<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

it('snapshots cost_price to unit_cost_price line_cost and cost_total on createFromCart', function (): void {
    $variant = createTestVariant(['price' => 15000, 'cost_price' => 12000]);
    app(InventoryService::class)->restock($variant, 10);

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-cost-1@example.com',
        'guest_phone' => '01700000001',
    ]);

    $item = $order->fresh()->items()->firstOrFail();

    expect($item->unit_cost_price)->toBe(12000)
        ->and($item->line_cost)->toBe(24000)
        ->and($order->fresh()->cost_total)->toBe(24000);
});

it('snapshots decimal quantity cost correctly via bcmath', function (): void {
    $variant = createTestVariant(['price' => 10000, 'cost_price' => 12000]);
    app(InventoryService::class)->restock($variant, '10.000');

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => '1.500',
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-cost-2@example.com',
        'guest_phone' => '01700000002',
    ]);

    $item = $order->fresh()->items()->firstOrFail();

    // 12000 * 1.500 = 18000
    expect($item->quantity)->toBe('1.500')
        ->and($item->unit_cost_price)->toBe(12000)
        ->and($item->line_cost)->toBe(18000)
        ->and($order->fresh()->cost_total)->toBe(18000);
});

it('stores zero cost when variant cost_price is null (unvalued)', function (): void {
    $variant = createTestVariant(['price' => 10000, 'cost_price' => null]);
    app(InventoryService::class)->restock($variant, 5);

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-cost-3@example.com',
        'guest_phone' => '01700000003',
    ]);

    $item = $order->fresh()->items()->firstOrFail();

    expect($item->unit_cost_price)->toBe(0)
        ->and($item->line_cost)->toBe(0)
        ->and($order->fresh()->cost_total)->toBe(0);
});

it('keeps cost snapshot immutable after variant cost_price changes', function (): void {
    $variant = createTestVariant(['price' => 15000, 'cost_price' => 10000]);
    app(InventoryService::class)->restock($variant, 10);

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-cost-4@example.com',
        'guest_phone' => '01700000004',
    ]);

    // Change variant cost after order
    $variant->update(['cost_price' => 20000]);

    $item = $order->fresh()->items()->firstOrFail();
    expect($item->unit_cost_price)->toBe(10000)
        ->and($item->line_cost)->toBe(20000)
        ->and($order->fresh()->cost_total)->toBe(20000);
});

it('rolls up cost_total when adding items via addItem', function (): void {
    $variantA = createTestVariant(['price' => 10000, 'cost_price' => 5000]);
    app(InventoryService::class)->restock($variantA, 10);
    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variantA->id,
        'quantity' => 2,
        'unit_price' => $variantA->price,
    ]);
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-cost-5@example.com',
        'guest_phone' => '01700000005',
    ]);

    expect($order->fresh()->cost_total)->toBe(10000); // 5000*2

    $variantB = createTestVariant(['price' => 20000, 'cost_price' => 8000]);
    app(InventoryService::class)->restock($variantB, 10);

    app(OrderService::class)->addItem($order, $variantB, 1);

    expect($order->fresh()->cost_total)->toBe(18000); // 10000 + 8000
    expect($order->fresh()->items()->where('product_variant_id', $variantB->id)->first()->unit_cost_price)->toBe(8000);
});

it('updates cost snapshot when swapping variant via changeItemVariant', function (): void {
    $variantA = createTestVariant(['price' => 10000, 'cost_price' => 4000]);
    app(InventoryService::class)->restock($variantA, 10);
    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variantA->id,
        'quantity' => 2,
        'unit_price' => $variantA->price,
    ]);
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-cost-6@example.com',
        'guest_phone' => '01700000006',
    ]);

    $item = $order->fresh()->items()->firstOrFail();
    expect($item->unit_cost_price)->toBe(4000)
        ->and($item->line_cost)->toBe(8000);

    $variantB = createTestVariant(['price' => 20000, 'cost_price' => 9000]);
    app(InventoryService::class)->restock($variantB, 10);

    app(OrderService::class)->changeItemVariant($order, $item, $variantB);

    $freshItem = $order->fresh()->items()->firstOrFail();
    expect($freshItem->product_variant_id)->toBe($variantB->id)
        ->and($freshItem->unit_cost_price)->toBe(9000)
        ->and($freshItem->line_cost)->toBe(18000)
        ->and($order->fresh()->cost_total)->toBe(18000);
});

it('does not mutate cost_total on cancellation', function (): void {
    $variant = createTestVariant(['price' => 10000, 'cost_price' => 6000]);
    app(InventoryService::class)->restock($variant, 10);
    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => $variant->price,
    ]);
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-cost-7@example.com',
        'guest_phone' => '01700000007',
    ]);

    $costBefore = $order->fresh()->cost_total;
    expect($costBefore)->toBe(12000);

    app(OrderService::class)->cancelOrder($order, 'Customer request');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cost_total)->toBe(12000)
        ->and($order->fresh()->items()->firstOrFail()->line_cost)->toBe(12000);
});
