<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsTenant();
});

function createPendingOrderWithGatewayPayment(int $grandTotal = 100000): Order
{
    $method = PaymentMethod::query()->create([
        'tenant_id' => tenant()->id,
        'name' => 'Online', 
        'type' => 'aggregator', 
        'gateway_driver' => 'sslcommerz', 
        'is_active' => true,
    ]);

    return Order::factory()->create([
        'tenant_id' => tenant()->id,
        'payment_method_id' => $method->id,
        'order_number' => 'ORD-' . Str::random(8),
        'grand_total' => $grandTotal,
        'status' => OrderStatus::Pending,
    ]);
}

it('marks the order paid and confirmed on a valid gateway callback', function () {
    $order = createPendingOrderWithGatewayPayment(100000);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    Http::fake([
        '*' => Http::response([
            'status' => 'VALID',
            'tran_id' => $tranId,
            'amount' => 100000,
            'currency_amount' => 100000,
        ]),
    ]);

    app(PaymentGatewayService::class)->handleCallback('val_123', $tranId);

    $payment = OrderPayment::query()->where('transaction_reference', $tranId)->first();
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe(OrderPaymentStatus::Paid);
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

it('is idempotent across duplicate callbacks for the same transaction', function () {
    $order = createPendingOrderWithGatewayPayment(100000);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    Http::fake([
        '*' => Http::response([
            'status' => 'VALID',
            'tran_id' => $tranId,
            'amount' => 100000,
            'currency_amount' => 100000,
        ]),
    ]);

    app(PaymentGatewayService::class)->handleCallback('val_123', $tranId);
    app(PaymentGatewayService::class)->handleCallback('val_123', $tranId);

    expect(OrderPayment::query()->where('transaction_reference', $tranId)->count())->toBe(1);
});

it('marks the payment failed when the validated amount does not match the order', function () {
    $order = createPendingOrderWithGatewayPayment(100000);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    Http::fake([
        '*' => Http::response([
            'status' => 'VALID',
            'tran_id' => $tranId,
            'amount' => 50000,
            'currency_amount' => 50000,
        ]),
    ]);

    app(PaymentGatewayService::class)->handleCallback('val_123', $tranId);

    $payment = OrderPayment::query()->where('transaction_reference', $tranId)->first();
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe(OrderPaymentStatus::Failed);
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});