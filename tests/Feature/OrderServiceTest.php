<?php

use App\Enums\OrderEventType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    actingAsTenant();
});

it('rejects guest checkout without required contact info', function () {
    [$cart] = createCartWithVariant();

    app(OrderService::class)->createFromCart($cart, []);
})->throws(InvalidArgumentException::class);

it('creates an order, reserves stock, and dispatches OrderPlaced', function () {
    Event::fake([OrderPlaced::class]);
    [$cart, $variant] = createCartWithVariant(2);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Buyer', 'guest_email' => 'buyer@example.com', 'guest_phone' => '01700000000',
    ], OrderSource::Website);

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->unit_price)->toBe($variant->price);
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(2);
    expect($order->fulfillments)->toHaveCount(1);
    Event::assertDispatched(OrderPlaced::class);
});

it('rejects a stale cart price without creating an order or reservation', function () {
    [$cart, $variant] = createCartWithVariant();
    $cartPrice = $variant->price;

    $variant->update(['price' => $cartPrice + 100]);

    expect(fn () => app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Buyer', 'guest_email' => 'buyer@example.com', 'guest_phone' => '01700000000',
    ]))->toThrow(InvalidOrderStateException::class, 'has changed');

    expect(Order::query()->count())->toBe(0);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect($cart->fresh()->converted_at)->toBeNull();
    expect($cart->items()->first()->fresh()->unit_price)->toBe($cartPrice);
});

it('rejects a cross-tenant variant without creating an order', function () {
    $tenant = tenant();
    $cart = Cart::query()->create([
        'tenant_id' => $tenant->id,
        'currency_code' => 'BDT',
    ]);

    $foreignTenant = Tenant::factory()->create();
    app(Tenancy::class)->set($foreignTenant);
    $foreignVariant = createTestVariant();

    app(Tenancy::class)->set($tenant);
    CartItem::query()->create([
        'tenant_id' => $tenant->id,
        'cart_id' => $cart->id,
        'product_variant_id' => $foreignVariant->id,
        'quantity' => 1,
        'unit_price' => $foreignVariant->price,
    ]);

    expect(fn () => app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Buyer', 'guest_email' => 'buyer@example.com', 'guest_phone' => '01700000000',
    ]))->toThrow(InvalidOrderStateException::class);

    expect(Order::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
    expect($cart->fresh()->converted_at)->toBeNull();
});

it('rejects a competing stock reservation without creating a second order', function () {
    [$firstCart, $variant] = createCartWithVariant();
    $variant->stockItems()->first()->update(['quantity' => 1]);

    $secondCart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'currency_code' => 'BDT',
    ]);
    $secondCart->items()->create([
        'tenant_id' => $secondCart->tenant_id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => $variant->price,
    ]);

    $orderData = [
        'guest_name' => 'Test Buyer', 'guest_email' => 'buyer@example.com', 'guest_phone' => '01700000000',
    ];
    $orders = app(OrderService::class);

    $orders->createFromCart($firstCart, $orderData);

    // A different identity competes for the same scarce stock — the reservation
    // limit is intentionally not involved here.
    expect(fn () => $orders->createFromCart($secondCart, [
        'guest_name' => 'Second Buyer', 'guest_email' => 'second@example.com', 'guest_phone' => '01800000000',
    ]))
        ->toThrow(InvalidOrderStateException::class);

    expect(Order::query()->count())->toBe(1);
    expect($secondCart->fresh()->converted_at)->toBeNull();
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
});

it('refuses an invalid status transition', function () {
    [$cart] = createCartWithVariant();
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'A', 'guest_email' => 'a@example.com', 'guest_phone' => '01700000000',
    ]);

    app(OrderService::class)->updateStatus($order, OrderStatus::Shipped);
})->throws(InvalidOrderTransitionException::class);

it('commits stock when an order is confirmed', function () {
    [$cart, $variant] = createCartWithVariant(3);
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'A', 'guest_email' => 'a@example.com', 'guest_phone' => '01700000000',
    ]);

    app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed);

    $stockItem = $variant->stockItems()->first()->fresh();
    expect($stockItem->quantity)->toBe(7);
    expect($stockItem->reserved_quantity)->toBe(0);
});

it('releases reserved stock when a pending order is cancelled', function () {
    Event::fake([OrderCancelled::class]);
    [$cart, $variant] = createCartWithVariant(2);
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'A', 'guest_email' => 'a@example.com', 'guest_phone' => '01700000000',
    ]);

    app(OrderService::class)->updateStatus($order, OrderStatus::Cancelled);

    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    Event::assertDispatched(OrderCancelled::class);
});

it('auto-cancels orders whose reservation has expired', function () {
    [$cart, $variant] = createCartWithVariant(2);
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'A', 'guest_email' => 'a@example.com', 'guest_phone' => '01700000000',
    ]);
    $order->update(['reservation_expires_at' => now()->subHour()]);

    $released = app(OrderService::class)->releaseExpiredReservations();

    expect($released)->toBe(1);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect(StockMovement::query()->where('product_variant_id', $variant->id)->where('type', 'release')->count())->toBe(1);

    expect(app(OrderService::class)->releaseExpiredReservations())->toBe(0);
    expect(StockMovement::query()->where('product_variant_id', $variant->id)->where('type', 'release')->count())->toBe(1);
});

it('releases every reservation in a multi-item expired order exactly once', function () {
    $firstVariant = createTestVariant();
    $secondVariant = createTestVariant();
    $inventory = app(InventoryService::class);
    $inventory->restock($firstVariant, 2);
    $inventory->restock($secondVariant, 3);

    $cart = Cart::query()->create(['tenant_id' => tenant()->id, 'currency_code' => 'BDT']);
    $cart->items()->createMany([
        [
            'tenant_id' => tenant()->id,
            'product_variant_id' => $firstVariant->id,
            'quantity' => 2,
            'unit_price' => $firstVariant->price,
        ],
        [
            'tenant_id' => tenant()->id,
            'product_variant_id' => $secondVariant->id,
            'quantity' => 3,
            'unit_price' => $secondVariant->price,
        ],
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'A', 'guest_email' => 'a@example.com', 'guest_phone' => '01700000000',
    ]);
    $order->update(['reservation_expires_at' => now()->subHour()]);

    expect(app(OrderService::class)->releaseExpiredReservations())->toBe(1);
    expect($firstVariant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect($secondVariant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect(StockMovement::query()->where('reference_id', $order->id)->where('type', 'release')->count())->toBe(2);
});

it('does not expire another tenant order', function () {
    $currentTenant = tenant();
    $otherTenant = Tenant::factory()->create();

    app(Tenancy::class)->set($otherTenant);
    [$cart, $variant] = createCartWithVariant();
    $otherOrder = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Other', 'guest_email' => 'other@example.com', 'guest_phone' => '01700000000',
    ]);
    $otherOrder->update(['reservation_expires_at' => now()->subHour()]);

    app(Tenancy::class)->set($currentTenant);

    expect(app(OrderService::class)->releaseExpiredReservations())->toBe(0);
    expect($otherOrder->fresh()->status)->toBe(OrderStatus::Pending);
    app(Tenancy::class)->set($otherTenant);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
});

it('rolls back expiration status when inventory release fails', function () {
    $variant = createTestVariant();
    app(InventoryService::class)->restock($variant, 1);

    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'reservation_expires_at' => now()->subHour(),
    ]);
    $order->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'product_name_snapshot' => 'Test',
        'variant_sku_snapshot' => $variant->sku,
        'unit_price' => $variant->price,
        'quantity' => 1,
        'line_total' => $variant->price,
    ]);
    app(InventoryService::class)->reserve($variant, 1, null, $order);

    $inventory = Mockery::mock(InventoryService::class);
    $inventory->shouldReceive('lockStockItemsForVariants')->andReturn(collect());
    $inventory->shouldReceive('release')->once()->andThrow(new RuntimeException('forced release failure'));
    app()->instance(InventoryService::class, $inventory);

    expect(fn () => app(OrderService::class)->releaseExpiredReservations())
        ->toThrow(RuntimeException::class, 'forced release failure');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
});

it('corrects the shipping address snapshot and logs an auditable event', function () {
    [$cart] = createCartWithVariant();
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'A', 'guest_email' => 'a@example.com', 'guest_phone' => '01700000000',
        'shipping_address' => [
            'recipient_name' => 'Original Name',
            'phone' => '01700000001',
            'address_line_1' => 'Old Street',
            'city' => 'Dhaka',
        ],
    ]);

    app(OrderService::class)->updateOrderShippingAddress($order, [
        'recipient_name' => 'Corrected Name',
        'phone' => '01700000002',
        'address_line_1' => 'New Street, Block B',
        'city' => 'Chattogram',
        'country' => 'BD',
    ]);

    expect($order->fresh()->shipping_address_snapshot)->toEqual([
        'recipient_name' => 'Corrected Name',
        'phone' => '01700000002',
        'address_line_1' => 'New Street, Block B',
        'city' => 'Chattogram',
        'country' => 'BD',
    ]);

    $event = $order->events()->where('type', OrderEventType::AddressUpdated)->first();
    expect($event)->not->toBeNull();
    expect($event->metadata['before']['address_line_1'])->toBe('Old Street');
    expect($event->metadata['after']['recipient_name'])->toBe('Corrected Name');

    // Order history (items, totals) must remain untouched.
    expect($order->fresh()->items)->toHaveCount(1);
});

it('corrects order contact and never touches the customer profile', function () {
    [$cart] = createCartWithVariant();
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Original Buyer',
        'guest_email' => 'original@example.com',
        'guest_phone' => '01700000001',
    ]);

    app(OrderService::class)->updateOrderContact($order, 'Corrected Buyer', 'corrected@example.com', '01800000000');

    expect($order->fresh()->guest_name)->toBe('Corrected Buyer');
    expect($order->fresh()->guest_email)->toBe('corrected@example.com');
    expect($order->fresh()->guest_phone)->toBe('01800000000');
    expect($order->fresh()->grand_total)->toBe($order->grand_total);

    $event = $order->events()->where('type', OrderEventType::ContactUpdated)->first();
    expect($event)->not->toBeNull();
    expect($event->metadata['before']['email'])->toBe('original@example.com');
    expect($event->metadata['after']['email'])->toBe('corrected@example.com');
});
