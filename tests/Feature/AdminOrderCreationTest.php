<?php

declare(strict_types=1);

use App\Enums\FulfillmentStrategy;
use App\Enums\OrderSource;
use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    Event::fake([OrderPlaced::class]);
    seedBootstrapPlans();
});

it('allows admin to create order with stock variant', function (): void {
    $tenant = actingAsTenant();
    [$cart, $variant] = createCartWithVariant(1);
    // Admin path uses direct lines, not cart
    $order = app(OrderService::class)->createFromAdmin(
        [['product_variant_id' => $variant->id, 'quantity' => 2]],
        [
            'tenant_id' => $tenant->id,
            'guest_name' => 'Admin Guest',
            'guest_email' => 'adminguest@example.com',
            'guest_phone' => '01700000000',
            'shipping_address' => ['recipient_name' => 'Admin Guest', 'phone' => '01700000000', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
        ],
        OrderSource::Admin,
    );

    expect($order->order_source->value)->toBe('admin');
    expect($order->items)->toHaveCount(1);
    expect($order->fulfillments)->toHaveCount(1);
});

it('allows admin to create mixed preorder order', function (): void {
    $tenant = actingAsTenant();
    $stock = createTestVariant(['fulfillment_strategy' => FulfillmentStrategy::Stock, 'price' => 1000]);
    app(InventoryService::class)->restock($stock, 5);
    $pre = createTestVariant(['fulfillment_strategy' => FulfillmentStrategy::Preorder, 'expected_available_at' => now()->addDays(10), 'price' => 2000]);

    $order = app(OrderService::class)->createFromAdmin(
        [
            ['product_variant_id' => $stock->id, 'quantity' => 1],
            ['product_variant_id' => $pre->id, 'quantity' => 1],
        ],
        [
            'tenant_id' => $tenant->id,
            'guest_name' => 'Mixed Guest',
            'guest_email' => 'mixedadmin@example.com',
            'guest_phone' => '01800000000',
            'shipping_address' => ['recipient_name' => 'Mixed', 'phone' => '01800000000', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
            'preorder_ack_at' => now(),
        ],
    );

    expect($order->fulfillments)->toHaveCount(2);
    expect($order->items->where('fulfillment_strategy', 'preorder'))->toHaveCount(1);
});
