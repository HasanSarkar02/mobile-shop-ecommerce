<?php

use App\Enums\OrderStatus;
use App\Exceptions\ReservationLimitExceededException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\SerialNumber;
use App\Models\Tenant;
use App\Services\OrderService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    actingAsTenant();
});

function checkoutOrderData(string $email): array
{
    return [
        'guest_name' => 'Test Buyer',
        'guest_email' => $email,
        'guest_phone' => '01700000000',
    ];
}

it('allows a guest to create one pending reservation', function () {
    [$cart, $variant] = createCartWithVariant();

    $order = app(OrderService::class)->createFromCart($cart, checkoutOrderData('first@example.com'));

    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->active_reservation_key)->toBe('guest:first@example.com');
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
    expect(Order::query()->count())->toBe(1);
});

it('rejects a second pending reservation for the same identity without reserving stock', function () {
    [$firstCart, $variant] = createCartWithVariant();
    app(OrderService::class)->createFromCart($firstCart, checkoutOrderData('first@example.com'));

    [$secondCart] = createCartWithVariant();
    expect(fn () => app(OrderService::class)->createFromCart($secondCart, checkoutOrderData('first@example.com')))
        ->toThrow(ReservationLimitExceededException::class);

    expect(Order::query()->count())->toBe(1);
    expect($secondCart->fresh()->converted_at)->toBeNull();
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
});

it('no longer counts an order toward the limit once it is cancelled', function () {
    [$firstCart, $variant] = createCartWithVariant();
    $firstOrder = app(OrderService::class)->createFromCart($firstCart, checkoutOrderData('retry@example.com'));

    app(OrderService::class)->updateStatus($firstOrder, OrderStatus::Cancelled);
    expect($firstOrder->fresh()->active_reservation_key)->toBeNull();
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);

    [$secondCart] = createCartWithVariant();
    $secondOrder = app(OrderService::class)->createFromCart($secondCart, checkoutOrderData('retry@example.com'));

    expect($secondOrder->status)->toBe(OrderStatus::Pending);
});

it('no longer counts an expired reservation and releases it during the next checkout', function () {
    [$firstCart, $variant] = createCartWithVariant();
    $firstOrder = app(OrderService::class)->createFromCart($firstCart, checkoutOrderData('expired@example.com'));
    $firstOrder->update(['reservation_expires_at' => now()->subHour()]);

    [$secondCart] = createCartWithVariant();
    $secondOrder = app(OrderService::class)->createFromCart($secondCart, checkoutOrderData('expired@example.com'));

    expect($secondOrder->status)->toBe(OrderStatus::Pending);
    expect($firstOrder->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($firstOrder->fresh()->active_reservation_key)->toBeNull();
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
});

it('no longer counts a confirmed order toward the limit', function () {
    [$firstCart, $variant] = createCartWithVariant();
    $firstOrder = app(OrderService::class)->createFromCart($firstCart, checkoutOrderData('paid@example.com'));

    app(OrderService::class)->updateStatus($firstOrder, OrderStatus::Confirmed);
    expect($firstOrder->fresh()->active_reservation_key)->toBeNull();
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);

    [$secondCart] = createCartWithVariant();
    $secondOrder = app(OrderService::class)->createFromCart($secondCart, checkoutOrderData('paid@example.com'));

    expect($secondOrder->status)->toBe(OrderStatus::Pending);
});

it('blocks a concurrent same-identity checkout via the unique reservation key', function () {
    [$cart, $variant] = createCartWithVariant();

    // Simulates the winning concurrent checkout: a pending order that already
    // holds the identity key. It uses a different guest email so the friendly
    // count pre-check cannot see it — only the database-level unique
    // (tenant_id, active_reservation_key) index can stop this race.
    $winner = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'guest_name' => 'Winner',
        'guest_email' => 'winner@example.com',
        'active_reservation_key' => 'guest:unique@example.com',
    ]);

    expect(fn () => app(OrderService::class)->createFromCart($cart, checkoutOrderData('unique@example.com')))
        ->toThrow(ReservationLimitExceededException::class);

    expect(Order::query()->where('guest_email', 'unique@example.com')->count())->toBe(0);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect($winner->fresh()->status)->toBe(OrderStatus::Pending);
});

it('is tenant-scoped: the same guest identity can reserve in another tenant', function () {
    $currentTenant = tenant();
    [$firstCart] = createCartWithVariant();
    app(OrderService::class)->createFromCart($firstCart, checkoutOrderData('iso@example.com'));

    $otherTenant = Tenant::factory()->create();
    app(Tenancy::class)->set($otherTenant);

    [$secondCart] = createCartWithVariant();
    $order = app(OrderService::class)->createFromCart($secondCart, checkoutOrderData('iso@example.com'));

    expect($order->status)->toBe(OrderStatus::Pending);
    expect(Order::query()->where('tenant_id', $otherTenant->id)->count())->toBe(1);
});

it('keeps a normal single-cart checkout working end to end', function () {
    [$cart, $variant] = createCartWithVariant(2);

    $order = app(OrderService::class)->createFromCart($cart, checkoutOrderData('normal@example.com'));

    expect($order->items)->toHaveCount(1);
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(2);
    expect($order->fulfillments)->toHaveCount(1);
});

it('keeps serialized reservations intact when a payment-failure cancellation releases them', function () {
    $variant = createTestVariant(['inventory_type' => 'serialized']);
    SerialNumber::factory()->count(3)->for($variant, 'variant')->create(['status' => 'available']);

    $cart = Cart::query()->create(['tenant_id' => tenant()->id, 'currency_code' => 'BDT']);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, checkoutOrderData('serial@example.com'));

    expect(app(OrderService::class)->cancelPendingOrderReservation($order, 'Order cancelled — payment failed.'))->toBeTrue();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'available')->count())->toBe(3);
    expect(SerialNumber::query()->where('product_variant_id', $variant->id)->whereNotNull('order_item_id')->count())->toBe(0);
});

it('keeps the P0-B expiration release behavior correct', function () {
    [$cart, $variant] = createCartWithVariant(2);
    $order = app(OrderService::class)->createFromCart($cart, checkoutOrderData('expire@example.com'));
    $order->update(['reservation_expires_at' => now()->subHour()]);

    expect(app(OrderService::class)->releaseExpiredReservations())->toBe(1);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($order->fresh()->active_reservation_key)->toBeNull();
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
});
