<?php

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