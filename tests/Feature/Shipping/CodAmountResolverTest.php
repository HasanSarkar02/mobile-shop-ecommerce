<?php

declare(strict_types=1);

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\PaymentMethod;
use App\Services\Shipping\CodAmountResolver;
use Illuminate\Support\Str;

beforeEach(function (): void {
    actingAsTenant();
});

function codMethod(PaymentMethodType $type = PaymentMethodType::Cod): PaymentMethod
{
    return PaymentMethod::query()->create([
        'tenant_id' => tenant()->id,
        'name' => $type->value,
        'type' => $type,
        'is_active' => true,
    ]);
}

/**
 * Orders are built directly rather than through createFromCart so each test can
 * state the exact grand_total and consignment split the allocation math is about.
 */
function codOrder(int $grandTotal, ?PaymentMethod $method): Order
{
    return Order::query()->create([
        'tenant_id' => tenant()->id,
        'order_number' => 'ORD-COD-'.Str::random(8),
        'status' => OrderStatus::Shipped,
        'payment_method_id' => $method?->id,
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ]);
}

function codFulfillment(Order $order, int $lineTotal, OrderFulfillmentStatus $status = OrderFulfillmentStatus::Shipped): OrderFulfillment
{
    $fulfillment = $order->fulfillments()->create([
        'tenant_id' => $order->tenant_id,
        'status' => $status,
        'fulfillment_group' => 'stock',
    ]);

    $order->items()->create([
        'tenant_id' => $order->tenant_id,
        'order_fulfillment_id' => $fulfillment->id,
        'product_variant_id' => createTestVariant()->id,
        'product_name_snapshot' => 'Test Item',
        'variant_sku_snapshot' => 'SKU-'.Str::random(6),
        'unit_price' => $lineTotal,
        'quantity' => 1,
        'line_total' => $lineTotal,
        'fulfillment_strategy' => 'stock',
    ]);

    return $fulfillment;
}

it('collects nothing on a non-COD order', function (): void {
    // An abandoned prepaid order must never be silently converted to
    // cash-on-delivery at the customer's door.
    $order = codOrder(500_00, codMethod(PaymentMethodType::OnlineGateway));
    $fulfillment = codFulfillment($order, 500_00);

    expect(app(CodAmountResolver::class)->minorUnitsFor($order, $fulfillment))->toBe(0);
});

it('collects nothing when the order has no payment method at all', function (): void {
    $order = codOrder(500_00, null);
    $fulfillment = codFulfillment($order, 500_00);

    expect(app(CodAmountResolver::class)->minorUnitsFor($order, $fulfillment))->toBe(0);
});

it('gives a single consignment the whole due', function (): void {
    $order = codOrder(1_250_00, codMethod());
    $fulfillment = codFulfillment($order, 1_250_00);

    expect(app(CodAmountResolver::class)->minorUnitsFor($order, $fulfillment))->toBe(1_250_00);
});

it('nets out cash and card already paid', function (): void {
    $method = codMethod();
    $order = codOrder(1_000_00, $method);
    $fulfillment = codFulfillment($order, 1_000_00);

    $order->payments()->create([
        'tenant_id' => $order->tenant_id,
        'payment_method_id' => $method->id,
        'amount' => 400_00,
        'status' => OrderPaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    expect(app(CodAmountResolver::class)->minorUnitsFor($order, $fulfillment))->toBe(600_00);
});

it('splits the due across consignments instead of collecting it twice', function (): void {
    // The bug this closes: both drivers used to hand every consignment the whole
    // order due, so a two-consignment order tried to collect the total twice.
    $order = codOrder(1_000_00, codMethod());
    $a = codFulfillment($order, 600_00);
    $b = codFulfillment($order, 400_00);

    $resolver = app(CodAmountResolver::class);
    $forA = $resolver->minorUnitsFor($order, $a);
    $forB = $resolver->minorUnitsFor($order, $b);

    expect($forA)->toBe(600_00);
    expect($forB)->toBe(400_00);
    expect($forA + $forB)->toBe(1_000_00);
});

it('creates or loses no paisa when the due does not divide evenly', function (): void {
    // 1000 minor units over three equal consignments is 333.33…: largest-remainder
    // must put the stray unit somewhere, and the parts must still sum exactly.
    $order = codOrder(1000, codMethod());
    $a = codFulfillment($order, 100);
    $b = codFulfillment($order, 100);
    $c = codFulfillment($order, 100);

    $resolver = app(CodAmountResolver::class);
    $parts = [
        $resolver->minorUnitsFor($order, $a),
        $resolver->minorUnitsFor($order, $b),
        $resolver->minorUnitsFor($order, $c),
    ];

    expect(array_sum($parts))->toBe(1000);
    expect($parts)->toEqualCanonicalizing([334, 333, 333]);
    // Ties break on the lowest id, so the extra unit is deterministic.
    expect($parts[0])->toBe(334);
});

it('allocates the same amounts on every repeated call', function (): void {
    $order = codOrder(1000, codMethod());
    $a = codFulfillment($order, 100);
    codFulfillment($order, 100);
    codFulfillment($order, 100);

    $resolver = app(CodAmountResolver::class);

    expect($resolver->minorUnitsFor($order, $a))
        ->toBe($resolver->minorUnitsFor($order->fresh(), $a));
});

it('collects nothing for a consignment that already arrived', function (): void {
    $order = codOrder(1_000_00, codMethod());
    $delivered = codFulfillment($order, 600_00, OrderFulfillmentStatus::Delivered);
    $inTransit = codFulfillment($order, 400_00);

    $resolver = app(CodAmountResolver::class);

    expect($resolver->minorUnitsFor($order, $delivered))->toBe(0);
    // The still-moving consignment carries the whole remaining due: cash already
    // collected has already reduced it via amountPaid().
    expect($resolver->minorUnitsFor($order, $inTransit))->toBe(1_000_00);
});

it('collects nothing for a returned consignment', function (): void {
    $order = codOrder(1_000_00, codMethod());
    $failed = codFulfillment($order, 600_00, OrderFulfillmentStatus::Failed);
    codFulfillment($order, 400_00);

    expect(app(CodAmountResolver::class)->minorUnitsFor($order, $failed))->toBe(0);
});

it('collects nothing once the order is fully paid', function (): void {
    $method = codMethod();
    $order = codOrder(500_00, $method);
    $fulfillment = codFulfillment($order, 500_00);

    $order->payments()->create([
        'tenant_id' => $order->tenant_id,
        'payment_method_id' => $method->id,
        'amount' => 500_00,
        'status' => OrderPaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    expect(app(CodAmountResolver::class)->minorUnitsFor($order, $fulfillment))->toBe(0);
});

it('splits evenly when items were never linked to a consignment', function (): void {
    // Legacy rows carry no order_fulfillment_id, so there is nothing to weigh by.
    // Splitting evenly beats loading the entire due onto one consignment.
    $order = codOrder(1_000_00, codMethod());

    $a = $order->fulfillments()->create(['tenant_id' => $order->tenant_id, 'status' => OrderFulfillmentStatus::Shipped, 'fulfillment_group' => 'stock']);
    $b = $order->fulfillments()->create(['tenant_id' => $order->tenant_id, 'status' => OrderFulfillmentStatus::Shipped, 'fulfillment_group' => 'preorder']);

    $resolver = app(CodAmountResolver::class);

    expect($resolver->minorUnitsFor($order, $a))->toBe(500_00);
    expect($resolver->minorUnitsFor($order, $b))->toBe(500_00);
});

it('gives the whole due to a caller with no consignment split to respect', function (): void {
    $order = codOrder(750_00, codMethod());

    expect(app(CodAmountResolver::class)->minorUnitsFor($order, null))->toBe(750_00);
});
