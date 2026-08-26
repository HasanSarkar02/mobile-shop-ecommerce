<?php

declare(strict_types=1);

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\StockItem;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

function refundMethod(): PaymentMethod
{
    return PaymentMethod::query()->create([
        'tenant_id' => tenant()->id,
        'name' => 'Cash on Delivery',
        'type' => PaymentMethodType::Cod,
        'is_active' => true,
    ]);
}

/**
 * A genuinely delivered, fully-paid order: every transition and the stock commit
 * go through the services, so the ledger under test is the real one.
 *
 * @return array{0: Order, 1: ProductVariant}
 */
function refundDeliveredOrder(int $quantity = 2): array
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
    $orders->updateStatus($order->fresh(), OrderStatus::Delivered);

    $order = $order->fresh();
    $orders->recordPayment($order, refundMethod(), (int) $order->grand_total, OrderPaymentStatus::Paid, 'cod:paid');

    return [$order->fresh(), $variant];
}

/**
 * @return array{0: Order, 1: ProductVariant}
 */
function refundDeliveredSerializedOrder(int $quantity = 2, int $serialCount = 3): array
{
    $variant = createTestVariant(['inventory_type' => 'serialized']);
    SerialNumber::factory()->count($serialCount)->for($variant, 'variant')->create(['status' => 'available']);

    $cart = Cart::query()->create(['tenant_id' => tenant()->id, 'customer_id' => null, 'currency_code' => 'BDT']);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'unit_price' => $variant->price,
    ]);

    $orders = app(OrderService::class);
    $order = $orders->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '01700000000',
    ]);

    $orders->updateStatus($order, OrderStatus::Confirmed);
    $orders->updateStatus($order->fresh(), OrderStatus::Processing);
    $orders->updateStatus($order->fresh(), OrderStatus::Shipped);
    $orders->updateStatus($order->fresh(), OrderStatus::Delivered);

    $order = $order->fresh();
    $orders->recordPayment($order, refundMethod(), (int) $order->grand_total, OrderPaymentStatus::Paid, 'cod:paid');

    return [$order->fresh(), $variant];
}

function refundStockQuantity(ProductVariant $variant): int
{
    return (int) StockItem::query()->where('product_variant_id', $variant->id)->value('quantity');
}

describe('restock on a full refund', function (): void {
    it('returns the refunded quantity to the sellable pool', function (): void {
        [$order, $variant] = refundDeliveredOrder(2);
        $before = refundStockQuantity($variant);

        app(OrderService::class)->refund($order, (int) $order->grand_total, 'Customer returned the goods.', restock: true);

        expect(refundStockQuantity($variant))->toBe($before + 2);
    });

    it('still moves the order to refunded', function (): void {
        [$order] = refundDeliveredOrder();

        app(OrderService::class)->refund($order, (int) $order->grand_total, 'Customer returned the goods.', restock: true);

        expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
    });

    it('proves in the audit trail that the goods came back', function (): void {
        // Without this the ledger cannot answer "was this order restocked?", and a
        // second well-meaning restock would double-count stock.
        [$order] = refundDeliveredOrder();

        app(OrderService::class)->refund($order, (int) $order->grand_total, 'Customer returned the goods.', restock: true);

        $event = $order->events()
            ->where('type', 'payment_recorded')
            ->where('metadata->restocked', true)
            ->first();

        expect($event)->not->toBeNull();
    });

    it('returns the exact serials that were sold, not arbitrary ones', function (): void {
        [$order, $variant] = refundDeliveredSerializedOrder(2, 3);

        $item = $order->items()->firstOrFail();
        $sold = SerialNumber::query()
            ->where('order_item_id', $item->id)
            ->orderBy('id')
            ->pluck('imei_or_serial')
            ->all();

        expect($sold)->toHaveCount(2);

        app(OrderService::class)->refund($order, (int) $order->grand_total, 'Customer returned the handsets.', restock: true);

        $returned = SerialNumber::query()->whereIn('imei_or_serial', $sold)->get();

        expect($returned)->toHaveCount(2);
        foreach ($returned as $serial) {
            expect($serial->order_item_id)->toBeNull();
            expect($serial->sold_at)->toBeNull();
        }

        // The third serial was never sold, so all three are available again.
        expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'available')->count())->toBe(3);
    });

    it('names the returned serials in the refund event', function (): void {
        [$order] = refundDeliveredSerializedOrder(2, 3);
        $sku = $order->items()->firstOrFail()->variant_sku_snapshot;

        app(OrderService::class)->refund($order, (int) $order->grand_total, 'Customer returned the handsets.', restock: true);

        $event = $order->events()->where('metadata->restocked', true)->firstOrFail();

        expect($event->metadata['returned_serials'][$sku] ?? null)->toHaveCount(2);
    });
});

describe('when restock must be refused', function (): void {
    it('refuses to restock a cancelled order that was already restocked', function (): void {
        // Cancelling from Confirmed/Processing/Shipped already returned the goods.
        // Doing it again on the refund would invent stock that does not exist.
        [$cart, $variant] = createCartWithVariant(2);
        $orders = app(OrderService::class);
        $order = $orders->createFromCart($cart, [
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '01700000000',
        ]);
        $orders->updateStatus($order, OrderStatus::Confirmed);
        $orders->recordPayment($order->fresh(), refundMethod(), (int) $order->grand_total, OrderPaymentStatus::Paid, 'paid-1');
        $orders->cancelOrder($order->fresh(), 'Courier returned everything.');

        $order = $order->fresh();
        $afterCancel = refundStockQuantity($variant);

        expect(fn () => $orders->refund($order, (int) $order->grand_total, 'Refunding the cancelled order.', restock: true))
            ->toThrow(InvalidOrderStateException::class, 'already restocked when it was cancelled');

        // The whole refund is rolled back, not half-applied.
        expect(refundStockQuantity($variant))->toBe($afterCancel);
        expect(app(OrderService::class)->amountRefunded($order->fresh()))->toBe(0);
    });

    it('refuses to guess which items a partial refund returned', function (): void {
        [$order, $variant] = refundDeliveredOrder(2);
        $before = refundStockQuantity($variant);

        expect(fn () => app(OrderService::class)->refund($order, 100, 'Goodwill for a scratch.', restock: true))
            ->toThrow(InvalidOrderStateException::class, 'Refund in full to return goods to stock');

        expect(refundStockQuantity($variant))->toBe($before);
        expect($order->fresh()->payments()->where('status', OrderPaymentStatus::Refunded)->count())->toBe(0);
    });

    it('refuses to restock goods that never reached the customer', function (): void {
        [$cart] = createCartWithVariant(2);
        $orders = app(OrderService::class);
        $order = $orders->createFromCart($cart, [
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '01700000000',
        ]);
        $orders->updateStatus($order, OrderStatus::Confirmed);
        $orders->recordPayment($order->fresh(), refundMethod(), (int) $order->grand_total, OrderPaymentStatus::Paid, 'paid-2');
        $orders->updateStatus($order->fresh(), OrderStatus::Processing);
        $orders->updateStatus($order->fresh(), OrderStatus::Shipped);

        $shipped = $order->fresh();

        expect(fn () => $orders->refund($shipped, (int) $shipped->grand_total, 'Refund mid-transit.', restock: true))
            ->toThrow(InvalidOrderStateException::class, 'only be returned to stock on a delivered order');
    });
});

describe('refund without restock', function (): void {
    it('leaves stock alone and records nothing about restocking', function (): void {
        // The default. Money coming back does not mean goods came back.
        [$order, $variant] = refundDeliveredOrder(2);
        $before = refundStockQuantity($variant);

        app(OrderService::class)->refund($order, (int) $order->grand_total, 'Refunded to card, goods kept.');

        expect(refundStockQuantity($variant))->toBe($before);
        expect($order->fresh()->status)->toBe(OrderStatus::Refunded);

        $event = $order->events()->where('type', 'payment_recorded')->orderByDesc('id')->firstOrFail();
        expect($event->metadata)->not->toHaveKey('restocked');
        expect($event->metadata)->not->toHaveKey('returned_serials');
    });

    it('still supports a partial refund', function (): void {
        [$order] = refundDeliveredOrder();

        app(OrderService::class)->refund($order, 100, 'Goodwill for a scratch.');

        expect(app(OrderService::class)->amountRefunded($order->fresh()))->toBe(100);
        // A partial refund is not a full one, so the order stays Delivered.
        expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
    });
});

describe('over-refund protection', function (): void {
    it('cannot refund more than was ever paid', function (): void {
        [$order] = refundDeliveredOrder();

        expect(fn () => app(OrderService::class)->refund($order, (int) $order->grand_total + 1, 'Too much.'))
            ->toThrow(InvalidOrderStateException::class, 'exceeds refundable amount');
    });

    it('cannot restock twice by refunding twice', function (): void {
        // The second refund finds nothing refundable, which is also what stops a
        // second restock — the two guards are the same guard.
        [$order, $variant] = refundDeliveredOrder(2);
        $before = refundStockQuantity($variant);

        app(OrderService::class)->refund($order, (int) $order->grand_total, 'Customer returned the goods.', restock: true);

        expect(fn () => app(OrderService::class)->refund($order->fresh(), 100, 'Again.', restock: true))
            ->toThrow(InvalidOrderStateException::class, 'No refundable amount remains');

        expect(refundStockQuantity($variant))->toBe($before + 2);
    });

    it('refuses a zero refund', function (): void {
        [$order] = refundDeliveredOrder();

        expect(fn () => app(OrderService::class)->refund($order, 0, 'Nothing.'))
            ->toThrow(InvalidOrderStateException::class, 'greater than zero');
    });
});
