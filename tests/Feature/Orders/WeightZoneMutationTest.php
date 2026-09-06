<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

it('recomputes line_weight_grams on updateItemQuantity while preserving unit_weight_grams', function (): void {
    $variant = createTestVariant(['price' => 10000, 'cost_price' => 5000, 'weight_grams' => 400]);
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
        'guest_email' => 'guest-weight-mut-1@example.com',
        'guest_phone' => '01700000001',
    ]);

    $item = $order->fresh()->items()->firstOrFail();
    expect($item->unit_weight_grams)->toBe(400)
        ->and($item->line_weight_grams)->toBe(800)
        ->and($order->fresh()->total_weight_grams)->toBe(800);

    // Update quantity to 1.500
    app(OrderService::class)->updateItemQuantity($order, $item, '1.500');

    $freshItem = $item->fresh();
    expect($freshItem->unit_weight_grams)->toBe(400)
        ->and($freshItem->line_weight_grams)->toBe(600)
        ->and($freshItem->quantity)->toBe('1.500');

    expect($order->fresh()->total_weight_grams)->toBe(600);
});

it('updates weight snapshot when swapping variant via changeItemVariant', function (): void {
    $variantA = createTestVariant(['price' => 10000, 'cost_price' => 5000, 'weight_grams' => 300]);
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
        'guest_email' => 'guest-weight-mut-2@example.com',
        'guest_phone' => '01700000002',
    ]);

    $item = $order->fresh()->items()->firstOrFail();
    expect($item->unit_weight_grams)->toBe(300)
        ->and($item->line_weight_grams)->toBe(600);

    $variantB = createTestVariant(['price' => 20000, 'cost_price' => 15000, 'weight_grams' => 900]);
    app(InventoryService::class)->restock($variantB, 10);

    app(OrderService::class)->changeItemVariant($order, $item, $variantB);

    $freshItem = $order->fresh()->items()->firstOrFail();
    expect($freshItem->unit_weight_grams)->toBe(900)
        ->and($freshItem->line_weight_grams)->toBe(1800)
        ->and($freshItem->product_variant_id)->toBe($variantB->id)
        ->and($freshItem->unit_cost_price)->toBe(15000)
        ->and($order->fresh()->total_weight_grams)->toBe(1800);
});
