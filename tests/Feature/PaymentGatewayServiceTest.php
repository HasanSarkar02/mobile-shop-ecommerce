<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Storefront\PaymentController;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\StockMovement;
use App\Services\OrderService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
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
        'order_number' => 'ORD-'.Str::random(8),
        'grand_total' => $grandTotal,
        'status' => OrderStatus::Pending,
    ]);
}

function createPendingStockOrderWithGateway(int $quantity = 1, string $email = 'buyer@example.com'): array
{
    $method = PaymentMethod::query()->create([
        'tenant_id' => tenant()->id,
        'name' => 'Online',
        'type' => 'aggregator',
        'gateway_driver' => 'sslcommerz',
        'is_active' => true,
    ]);

    [$cart, $variant] = createCartWithVariant($quantity);
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Buyer',
        'guest_email' => $email,
        'guest_phone' => '01700000000',
        'payment_method_id' => $method->id,
    ]);

    return [$order, $variant];
}

it('marks the order paid and confirmed on a valid gateway callback', function () {
    $order = createPendingOrderWithGatewayPayment(100000);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    // The driver converts the gateway amount (TAKA) to paise (x100), so 1000
    // TAKA matches the order's 100000 paise grand total.
    Http::fake([
        '*' => Http::response([
            'status' => 'VALID',
            'tran_id' => $tranId,
            'amount' => 1000,
            'currency_amount' => 1000,
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

it('cancels the pending order when the validated amount does not match', function () {
    $order = createPendingOrderWithGatewayPayment(100000);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    // 500 TAKA (=> 50000 paise) does not match the 100000 paise grand total.
    Http::fake([
        '*' => Http::response([
            'status' => 'VALID',
            'tran_id' => $tranId,
            'amount' => 500,
            'currency_amount' => 500,
        ]),
    ]);

    app(PaymentGatewayService::class)->handleCallback('val_123', $tranId);

    $payment = OrderPayment::query()->where('transaction_reference', $tranId)->first();
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe(OrderPaymentStatus::Failed);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('cancels a pending order and releases stock when payment fails', function () {
    [$order, $variant] = createPendingStockOrderWithGateway(2);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    app(PaymentGatewayService::class)->markFailed($tranId, 'Order cancelled — payment failed.');

    expect(OrderPayment::query()->where('transaction_reference', $tranId)->first()->status)->toBe(OrderPaymentStatus::Failed);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
});

it('cancels a pending order and releases stock when the customer cancels payment', function () {
    [$order, $variant] = createPendingStockOrderWithGateway(2);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    $request = Request::create('/payment/cancel', 'POST', ['tran_id' => $tranId]);
    $response = app(PaymentController::class)->cancel($request, app(PaymentGatewayService::class));

    expect($response->getStatusCode())->toBe(302);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
});

it('is idempotent across repeated fail callbacks', function () {
    [$order, $variant] = createPendingStockOrderWithGateway(2);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    $service = app(PaymentGatewayService::class);
    $service->markFailed($tranId, 'Order cancelled — payment failed.');
    $service->markFailed($tranId, 'Order cancelled — payment failed.');
    $service->markFailed($tranId, 'Order cancelled — payment failed.');

    expect(OrderPayment::query()->where('transaction_reference', $tranId)->count())->toBe(1);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect(StockMovement::query()->where('reference_id', $order->id)->where('type', 'release')->count())->toBe(1);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
});

it('does not affect another order when releasing a failed payment', function () {
    [$orderA, $variantA] = createPendingStockOrderWithGateway(2, 'a@example.com');
    [$orderB, $variantB] = createPendingStockOrderWithGateway(1, 'b@example.com');

    $tranIdA = "{$orderA->tenant_id}-{$orderA->order_number}";
    app(PaymentGatewayService::class)->markFailed($tranIdA, 'Order cancelled — payment failed.');

    expect($orderA->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($variantA->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);

    expect($orderB->fresh()->status)->toBe(OrderStatus::Pending);
    expect($variantB->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
});

it('does not touch a confirmed order when a fail callback arrives', function () {
    [$order, $variant] = createPendingStockOrderWithGateway(2);
    $tranId = "{$order->tenant_id}-{$order->order_number}";

    app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed);
    app(PaymentGatewayService::class)->markFailed($tranId, 'Order cancelled — payment failed.');

    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
});
