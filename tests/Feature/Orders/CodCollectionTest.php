<?php

declare(strict_types=1);

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\PaymentMethod;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

function codCollectionMethod(PaymentMethodType $type = PaymentMethodType::Cod): PaymentMethod
{
    return PaymentMethod::query()->create([
        'tenant_id' => tenant()->id,
        'name' => $type->value,
        'type' => $type,
        'is_active' => true,
    ]);
}

/**
 * @return array{0: Order, 1: OrderFulfillment}
 */
function codCollectionOrder(int $grandTotal = 1_000_00, OrderStatus $status = OrderStatus::Shipped): array
{
    $order = Order::query()->create([
        'tenant_id' => tenant()->id,
        'order_number' => 'ORD-CODC-'.Str::random(8),
        'status' => $status,
        'payment_method_id' => codCollectionMethod()->id,
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ]);

    $fulfillment = $order->fulfillments()->create([
        'tenant_id' => $order->tenant_id,
        'status' => OrderFulfillmentStatus::Shipped,
        'fulfillment_group' => 'stock',
    ]);

    return [$order, $fulfillment];
}

it('books the cash a courier collected against the consignment', function (): void {
    [$order, $fulfillment] = codCollectionOrder(1_000_00);

    $payment = app(OrderService::class)->recordCodCollection($fulfillment, 1_000_00);

    expect($payment)->not->toBeNull();
    expect($payment->amount)->toBe(1_000_00);
    expect($payment->status)->toBe(OrderPaymentStatus::Paid);
    expect($payment->paid_at)->not->toBeNull();
    // The reference is derived from the consignment, which is what makes the
    // existing unique (tenant_id, transaction_reference) index the idempotency key.
    expect($payment->transaction_reference)->toBe('cod:'.$fulfillment->id);
    expect(app(OrderService::class)->amountPaid($order))->toBe(1_000_00);
});

it('is a no-op when the same collection is recorded twice', function (): void {
    // The hourly poller can observe the same delivery repeatedly, and two app
    // servers can process the same tick. Neither may double-book the cash.
    [$order, $fulfillment] = codCollectionOrder(1_000_00);

    $first = app(OrderService::class)->recordCodCollection($fulfillment, 1_000_00);
    $second = app(OrderService::class)->recordCodCollection($fulfillment, 1_000_00);

    expect($first)->not->toBeNull();
    expect($second)->toBeNull();
    expect($order->payments()->count())->toBe(1);
    expect(app(OrderService::class)->amountPaid($order))->toBe(1_000_00);
});

it('records each consignment of a split order separately', function (): void {
    [$order, $first] = codCollectionOrder(1_000_00);

    $second = $order->fulfillments()->create([
        'tenant_id' => $order->tenant_id,
        'status' => OrderFulfillmentStatus::Shipped,
        'fulfillment_group' => 'preorder',
    ]);

    app(OrderService::class)->recordCodCollection($first, 600_00);
    app(OrderService::class)->recordCodCollection($second, 400_00);

    expect($order->payments()->count())->toBe(2);
    expect(app(OrderService::class)->amountPaid($order))->toBe(1_000_00);
});

it('accepts less cash than was due when the courier collected short', function (): void {
    // Partial collection is common, and is precisely why the amount is confirmed
    // by staff instead of being booked automatically at the full due.
    [$order, $fulfillment] = codCollectionOrder(1_000_00);

    app(OrderService::class)->recordCodCollection($fulfillment, 700_00);

    expect(app(OrderService::class)->amountPaid($order))->toBe(700_00);
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('refuses to collect more than the order is owed', function (): void {
    [, $fulfillment] = codCollectionOrder(1_000_00);

    expect(fn () => app(OrderService::class)->recordCodCollection($fulfillment, 1_200_00))
        ->toThrow(InvalidOrderStateException::class, 'exceeds the remaining due');
});

it('refuses to collect cash on a cancelled order', function (): void {
    [, $fulfillment] = codCollectionOrder(1_000_00, OrderStatus::Cancelled);

    expect(fn () => app(OrderService::class)->recordCodCollection($fulfillment, 1_000_00))
        ->toThrow(InvalidOrderStateException::class, 'cancelled order');
});

it('nets out money the customer already paid online', function (): void {
    [$order, $fulfillment] = codCollectionOrder(1_000_00);

    $order->payments()->create([
        'tenant_id' => $order->tenant_id,
        'payment_method_id' => $order->payment_method_id,
        'amount' => 400_00,
        'status' => OrderPaymentStatus::Paid,
        'paid_at' => now(),
        'transaction_reference' => 'gateway-'.Str::random(6),
    ]);

    app(OrderService::class)->recordCodCollection($fulfillment, 600_00);

    expect(app(OrderService::class)->amountPaid($order))->toBe(1_000_00);

    // A second consignment on a now fully-paid order has no cash left to collect.
    $second = $order->fulfillments()->create([
        'tenant_id' => $order->tenant_id,
        'status' => OrderFulfillmentStatus::Shipped,
        'fulfillment_group' => 'preorder',
    ]);

    expect(fn () => app(OrderService::class)->recordCodCollection($second, 100_00))
        ->toThrow(InvalidOrderStateException::class, 'already fully paid');
});

it('records a timeline event naming the collection', function (): void {
    [$order, $fulfillment] = codCollectionOrder(1_000_00);

    app(OrderService::class)->recordCodCollection($fulfillment, 1_000_00);

    expect($order->events()->where('type', 'payment_recorded')->count())->toBe(1);
});

it('refuses a zero or negative collection', function (): void {
    [, $fulfillment] = codCollectionOrder(1_000_00);

    expect(fn () => app(OrderService::class)->recordCodCollection($fulfillment, 0))
        ->toThrow(InvalidOrderStateException::class, 'greater than zero');
});
