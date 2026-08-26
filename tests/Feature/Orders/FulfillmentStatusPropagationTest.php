<?php

declare(strict_types=1);

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

/**
 * A real order taken all the way to Shipped through the service, so the
 * inventory ledger and status history are genuine rather than hand-set.
 *
 * @return array{0: Order, 1: ProductVariant}
 */
function propagationShippedOrder(int $quantity = 2): array
{
    [$cart, $variant] = createCartWithVariant($quantity);

    $orders = app(OrderService::class);
    $order = $orders->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '01700000000',
    ]);

    $orders->updateStatus($order, OrderStatus::Confirmed);
    $orders->updateStatus($order->fresh(), OrderStatus::Processing);
    $orders->updateStatus($order->fresh(), OrderStatus::Shipped);

    return [$order->fresh(), $variant];
}

function propagationExtraFulfillment(Order $order, string $group = 'preorder'): OrderFulfillment
{
    return $order->fulfillments()->create([
        'tenant_id' => $order->tenant_id,
        'status' => OrderFulfillmentStatus::Shipped,
        'fulfillment_group' => $group,
    ]);
}

describe('delivered propagation', function (): void {
    it('moves a shipped order to delivered when its only consignment arrives', function (): void {
        [$order] = propagationShippedOrder();
        $fulfillment = $order->fulfillments()->firstOrFail();

        app(OrderService::class)->updateFulfillment($fulfillment, OrderFulfillmentStatus::Delivered);

        expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
    });

    it('keeps the order shipped while any consignment is still in transit', function (): void {
        // Split orders legitimately deliver at different times, so one consignment
        // arriving says nothing about the order as a whole.
        [$order] = propagationShippedOrder();
        $first = $order->fulfillments()->firstOrFail();
        propagationExtraFulfillment($order);

        app(OrderService::class)->updateFulfillment($first, OrderFulfillmentStatus::Delivered);

        expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
    });

    it('moves the order to delivered once the last consignment arrives', function (): void {
        [$order] = propagationShippedOrder();
        $first = $order->fulfillments()->firstOrFail();
        $second = propagationExtraFulfillment($order);

        $orders = app(OrderService::class);
        $orders->updateFulfillment($first, OrderFulfillmentStatus::Delivered);
        $orders->updateFulfillment($second, OrderFulfillmentStatus::Delivered);

        expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
    });

    it('is a silent no-op when the same delivery is observed again', function (): void {
        // The poller re-reads the same courier status every hour. A repeat must
        // never raise InvalidOrderTransitionException.
        [$order] = propagationShippedOrder();
        $fulfillment = $order->fulfillments()->firstOrFail();

        $orders = app(OrderService::class);
        $orders->updateFulfillment($fulfillment, OrderFulfillmentStatus::Delivered);
        $orders->updateFulfillment($fulfillment->fresh(), OrderFulfillmentStatus::Delivered);

        expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
        expect($order->events()->where('type', 'status_changed')->where('to_status', 'delivered')->count())->toBe(1);
    });

    it('does not invent a shipped step for an order staff never shipped', function (): void {
        // Reaching Delivered from Processing would mean writing a Shipped event that
        // never happened. Leave the order where staff put it and say why.
        [$cart] = createCartWithVariant(2);
        $orders = app(OrderService::class);
        $order = $orders->createFromCart($cart, [
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '01700000000',
        ]);
        $orders->updateStatus($order, OrderStatus::Confirmed);
        $orders->updateStatus($order->fresh(), OrderStatus::Processing);

        $orders->updateFulfillment($order->fulfillments()->firstOrFail(), OrderFulfillmentStatus::Delivered);

        expect($order->fresh()->status)->toBe(OrderStatus::Processing);
        expect($order->events()->where('metadata->propagation', 'delivered_awaiting_shipped')->exists())->toBeTrue();
    });

    it('does not resurrect a cancelled order', function (): void {
        [$order] = propagationShippedOrder();
        $fulfillment = $order->fulfillments()->firstOrFail();

        app(OrderService::class)->cancelOrder($order, 'Customer changed their mind.');

        app(OrderService::class)->updateFulfillment($fulfillment->fresh(), OrderFulfillmentStatus::Delivered);

        expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    });
});

describe('failed propagation', function (): void {
    it('never auto-cancels when the courier returns everything', function (): void {
        // Cancelling is sticky: it restocks, releases the coupon, and Cancelled has
        // no path back to Delivered. A courier that later corrects itself would
        // leave the order permanently wrong, so a human decides.
        [$order, $variant] = propagationShippedOrder(2);
        $committedBefore = StockItem::query()->where('product_variant_id', $variant->id)->value('quantity');

        app(OrderService::class)->updateFulfillment($order->fulfillments()->firstOrFail(), OrderFulfillmentStatus::Failed);

        expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
        expect(StockItem::query()->where('product_variant_id', $variant->id)->value('quantity'))->toBe($committedBefore);
    });

    it('raises a financial adjustment naming the returned consignments', function (): void {
        [$order] = propagationShippedOrder();

        app(OrderService::class)->updateFulfillment($order->fulfillments()->firstOrFail(), OrderFulfillmentStatus::Failed);

        $event = $order->events()->where('metadata->propagation', 'all_consignments_failed')->first();

        expect($event)->not->toBeNull();
        expect($event->type->value)->toBe('financial_adjustment_required');
        expect($event->description)->toContain('returned every consignment');
    });

    it('flags a partial return without touching the order status', function (): void {
        [$order] = propagationShippedOrder();
        $first = $order->fulfillments()->firstOrFail();
        propagationExtraFulfillment($order);

        app(OrderService::class)->updateFulfillment($first, OrderFulfillmentStatus::Failed);

        expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
        expect($order->events()->where('metadata->propagation', 'partial_failure:'.$first->id)->exists())->toBeTrue();
        expect($order->events()->where('metadata->propagation', 'all_consignments_failed')->exists())->toBeFalse();
    });

    it('does not repeat the same notice on every poll', function (): void {
        // Without the once-only guard the hourly poller would bury the order
        // timeline in identical rows forever.
        [$order] = propagationShippedOrder();
        $fulfillment = $order->fulfillments()->firstOrFail();

        $orders = app(OrderService::class);
        $orders->updateFulfillment($fulfillment, OrderFulfillmentStatus::Failed);
        $orders->updateFulfillment($fulfillment->fresh(), OrderFulfillmentStatus::Failed);
        $orders->updateFulfillment($fulfillment->fresh(), OrderFulfillmentStatus::Failed);

        expect($order->events()->where('metadata->propagation', 'all_consignments_failed')->count())->toBe(1);
    });

    it('survives a courier flip-flopping from failed to delivered', function (): void {
        [$order] = propagationShippedOrder();
        $fulfillment = $order->fulfillments()->firstOrFail();

        $orders = app(OrderService::class);
        $orders->updateFulfillment($fulfillment, OrderFulfillmentStatus::Failed);
        // Because nothing was cancelled, the correction still lands cleanly.
        $orders->updateFulfillment($fulfillment->fresh(), OrderFulfillmentStatus::Delivered);

        expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
    });
});

it('keeps the caller instance in step with what was written', function (): void {
    [$order] = propagationShippedOrder();
    $fulfillment = $order->fulfillments()->firstOrFail();

    app(OrderService::class)->updateFulfillment($fulfillment, OrderFulfillmentStatus::Delivered, 'TRACK-'.Str::random(6), 'Test Courier');

    // The method locks and writes its own copy of the row, so the instance the
    // caller still holds must be refreshed from it rather than left stale.
    expect($fulfillment->status)->toBe(OrderFulfillmentStatus::Delivered);
    expect($fulfillment->courier_name)->toBe('Test Courier');
    expect($fulfillment->delivered_at)->not->toBeNull();
});
