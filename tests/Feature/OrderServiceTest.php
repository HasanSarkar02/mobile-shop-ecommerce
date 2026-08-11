<?php

use App\Enums\OrderEventType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Exceptions\InvalidOrderTransitionException;
use App\Services\OrderService;
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
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(2);
    expect($order->fulfillments)->toHaveCount(1);
    Event::assertDispatched(OrderPlaced::class);
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
