<?php

declare(strict_types=1);

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\ShippingMethodType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\ShippingMethod;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

function orderAdminCodMethod(): PaymentMethod
{
    return PaymentMethod::query()->create([
        'tenant_id' => tenant()->id,
        'name' => 'Cash on Delivery',
        'type' => PaymentMethodType::Cod,
        'is_active' => true,
    ]);
}

/**
 * @return array{0: Order, 1: ProductVariant}
 */
function orderAdminMakePendingOrder(int $quantity = 2): array
{
    [$cart, $variant] = createCartWithVariant($quantity);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '01700000000',
    ]);

    return [$order, $variant];
}

/**
 * @return array{0: Order, 1: ProductVariant}
 */
function orderAdminMakeSerializedOrder(int $quantity = 2, int $serialCount = 3): array
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

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '01700000000',
    ]);

    return [$order, $variant];
}

describe('ORDER 1A — line item editing and totals', function (): void {
    it('adds a line item to a pending order, reserves stock, and recalculates totals', function (): void {
        [$order, $variantA] = orderAdminMakePendingOrder(2);

        $variantB = createTestVariant();
        app(InventoryService::class)->restock($variantB, 10);

        app(OrderService::class)->addItem($order, $variantB, 3);

        $fresh = $order->fresh()->load('items');
        expect($fresh->items)->toHaveCount(2);
        expect(StockItem::query()->where('product_variant_id', $variantB->id)->value('reserved_quantity'))->toBe(3);

        $expectedSubtotal = ($variantA->price * 2) + ($variantB->price * 3);
        expect($fresh->subtotal)->toBe($expectedSubtotal);
        expect($fresh->grand_total)->toBe($expectedSubtotal);

        expect($fresh->events()->where('type', 'item_added')->count())->toBe(1);
    });

    it('removes a line item, releases its reservation, and recalculates totals', function (): void {
        [$order, $variant] = orderAdminMakePendingOrder(2);
        $item = $order->items()->first();

        app(OrderService::class)->removeItem($order, $item);

        $fresh = $order->fresh();
        expect($fresh->items)->toHaveCount(0);
        expect(StockItem::query()->where('product_variant_id', $variant->id)->value('reserved_quantity'))->toBe(0);
        expect($fresh->subtotal)->toBe(0);
        expect($fresh->grand_total)->toBe(0);

        expect($fresh->events()->where('type', 'item_removed')->count())->toBe(1);
    });

    it('adjusts line item quantity and moves reservations in both directions', function (): void {
        [$order, $variant] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);

        $orders->updateItemQuantity($order, $order->items()->first(), 5);
        expect(StockItem::query()->where('product_variant_id', $variant->id)->value('reserved_quantity'))->toBe(5);
        expect($order->fresh()->subtotal)->toBe($variant->price * 5);

        $orders->updateItemQuantity($order, $order->items()->first(), 1);
        expect(StockItem::query()->where('product_variant_id', $variant->id)->value('reserved_quantity'))->toBe(1);
        expect($order->fresh()->subtotal)->toBe($variant->price * 1);
    });

    it('refuses a quantity increase that exceeds available stock', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);

        expect(fn () => $orders->updateItemQuantity($order, $order->items()->first(), 999))
            ->toThrow(InsufficientStockException::class);

        $fresh = $order->fresh();
        expect($fresh->items()->first()->quantity)->toBe(1);
        expect($fresh->subtotal)->toBe($fresh->items()->first()->line_total);
    });

    it('swaps a line item to a different variant atomically', function (): void {
        [$order, $variantA] = orderAdminMakePendingOrder(2);

        $variantB = createTestVariant();
        app(InventoryService::class)->restock($variantB, 5);

        app(OrderService::class)->changeItemVariant($order, $order->items()->first(), $variantB);

        $item = $order->fresh()->items()->first();
        expect($item->product_variant_id)->toBe($variantB->id);
        expect($item->variant_sku_snapshot)->toBe($variantB->sku);
        expect($item->unit_price)->toBe($variantB->price);
        expect($item->line_total)->toBe($variantB->price * 2);

        expect(StockItem::query()->where('product_variant_id', $variantA->id)->value('reserved_quantity'))->toBe(0);
        expect(StockItem::query()->where('product_variant_id', $variantB->id)->value('reserved_quantity'))->toBe(2);
    });

    it('rolls back a variant swap when the new variant lacks stock', function (): void {
        [$order, $variantA] = orderAdminMakePendingOrder(1);
        $variantB = createTestVariant();

        expect(fn () => app(OrderService::class)->changeItemVariant($order, $order->items()->first(), $variantB))
            ->toThrow(InsufficientStockException::class);

        $fresh = $order->fresh();
        expect($fresh->items()->first()->product_variant_id)->toBe($variantA->id);
        expect(StockItem::query()->where('product_variant_id', $variantA->id)->value('reserved_quantity'))->toBe(1);
    });

    it('applies a unit price override only with a reason and logs before/after', function (): void {
        [$order, $variant] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);

        expect(fn () => $orders->adjustItemUnitPrice($order, $order->items()->first(), 5000, '   '))
            ->toThrow(InvalidArgumentException::class);

        $orders->adjustItemUnitPrice($order, $order->items()->first(), 5000, 'Manager override');

        $item = $order->fresh()->items()->first();
        expect($item->unit_price)->toBe(5000);
        expect($item->line_total)->toBe(10000);
        expect($order->fresh()->grand_total)->toBe(10000);

        $event = $order->events()->where('type', 'price_adjusted')->first();
        expect($event)->not->toBeNull();
        expect($event->metadata['reason'])->toBe('Manager override');
        expect($event->metadata['before_unit_price'])->toBe($variant->price);
        expect($event->metadata['after_unit_price'])->toBe(5000);
    });

    it('applies an order-level discount with a reason and refuses over-discounting', function (): void {
        [$order, $variant] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);

        $orders->applyOrderDiscount($order, 1000, 'Staff courtesy');

        $fresh = $order->fresh();
        expect($fresh->discount_total)->toBe(1000);
        expect($fresh->grand_total)->toBe(($variant->price * 2) - 1000);

        expect(fn () => $orders->applyOrderDiscount($order, ($variant->price * 2) + 1, 'too much'))
            ->toThrow(InvalidOrderStateException::class);
        expect($order->fresh()->discount_total)->toBe(1000);
    });

    it('updates shipping cost and method and recalculates the total', function (): void {
        [$order] = orderAdminMakePendingOrder(2);
        $method = ShippingMethod::query()->create([
            'tenant_id' => tenant()->id,
            'name' => 'Express',
            'type' => ShippingMethodType::FlatRate,
            'cost' => 0,
            'is_active' => true,
        ]);

        app(OrderService::class)->updateShipping($order, 1500, $method->id, 'Customer requested express');

        $fresh = $order->fresh();
        expect($fresh->shipping_cost)->toBe(1500);
        expect($fresh->shipping_method_id)->toBe($method->id);
        expect($fresh->grand_total)->toBe($fresh->subtotal + 1500);

        expect($fresh->events()->where('type', 'shipping_updated')->first()->metadata['after_cost'])->toBe(1500);
    });

    it('refuses to lower the total below the amount already paid', function (): void {
        [$order] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);
        $orders->recordPayment($order, orderAdminCodMethod(), $order->grand_total, OrderPaymentStatus::Paid);

        expect(fn () => $orders->removeItem($order, $order->items()->first()))
            ->toThrow(InvalidOrderStateException::class);
    });

    it('blocks line item and total edits once the order leaves pending', function (): void {
        [$order, $variant] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);
        $orders->updateStatus($order, OrderStatus::Confirmed);
        $item = $order->items()->first();

        expect(fn () => $orders->addItem($order, $variant, 1))->toThrow(InvalidOrderStateException::class);
        expect(fn () => $orders->updateItemQuantity($order, $item, 3))->toThrow(InvalidOrderStateException::class);
        expect(fn () => $orders->removeItem($order, $item))->toThrow(InvalidOrderStateException::class);
        expect(fn () => $orders->changeItemVariant($order, $item, $variant))->toThrow(InvalidOrderStateException::class);
        expect(fn () => $orders->adjustItemUnitPrice($order, $item, 1, 'x'))->toThrow(InvalidOrderStateException::class);
        expect(fn () => $orders->applyOrderDiscount($order, 1, 'x'))->toThrow(InvalidOrderStateException::class);
        expect(fn () => $orders->updateShipping($order, 1))->toThrow(InvalidOrderStateException::class);
    });

    it('appends an audit event with metadata for every line item operation', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);

        $item = $order->items()->first();
        $orders->updateItemQuantity($order, $item, 3);
        $orders->adjustItemUnitPrice($order, $item, 4000, 'override');
        $orders->applyOrderDiscount($order, 500, 'courtesy');
        $orders->updateShipping($order, 200, null, 'rush');

        $types = $order->fresh()->events->map(fn ($event): string => $event->type->value)->all();
        expect($types)->toContain('item_updated', 'price_adjusted', 'discount_adjusted', 'shipping_updated');

        $priceEvent = $order->events()->where('type', 'price_adjusted')->first();
        expect($priceEvent->metadata['reason'])->toBe('override');
    });
});

describe('ORDER 1B — payment hardening and address correction', function (): void {
    it('rejects a paid payment that exceeds the remaining due', function (): void {
        [$order] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);

        expect(fn () => $orders->recordPayment($order, orderAdminCodMethod(), $order->grand_total + 1, OrderPaymentStatus::Paid))
            ->toThrow(InvalidOrderStateException::class);
        expect($order->payments()->count())->toBe(0);
    });

    it('rejects zero and negative payment amounts', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);
        $method = orderAdminCodMethod();

        expect(fn () => $orders->recordPayment($order, $method, 0, OrderPaymentStatus::Paid))
            ->toThrow(InvalidOrderStateException::class);
        expect(fn () => $orders->recordPayment($order, $method, -100, OrderPaymentStatus::Paid))
            ->toThrow(InvalidOrderStateException::class);
        expect($order->payments()->count())->toBe(0);
    });

    it('supports partial payments up to the due amount and then refuses further payments', function (): void {
        [$order] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);
        $method = orderAdminCodMethod();
        $due = $order->grand_total;
        $half = (int) floor($due / 2);

        $orders->recordPayment($order, $method, $half, OrderPaymentStatus::Paid);
        expect($orders->amountPaid($order))->toBe($half);

        $orders->recordPayment($order, $method, $due - $half, OrderPaymentStatus::Paid);
        expect((int) $order->payments()->where('status', OrderPaymentStatus::Paid)->sum('amount'))->toBe($due);

        expect(fn () => $orders->recordPayment($order, $method, 1, OrderPaymentStatus::Paid))
            ->toThrow(InvalidOrderStateException::class);
    });

    it('rejects payments on a cancelled order', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);
        $orders->cancelOrder($order, 'Customer changed their mind');

        expect(fn () => $orders->recordPayment($order, orderAdminCodMethod(), 100, OrderPaymentStatus::Paid))
            ->toThrow(InvalidOrderStateException::class);
    });

    it('still records failed payments without the due cap', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);

        $orders->recordPayment($order, orderAdminCodMethod(), $order->grand_total + 9999, OrderPaymentStatus::Failed);

        expect($order->payments()->count())->toBe(1);
        expect($order->payments()->first()->status)->toBe(OrderPaymentStatus::Failed);
        expect($order->payments()->first()->paid_at)->toBeNull();
    });

    it('sets paid_at on paid payments', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);

        $paid = $orders->recordPayment($order, orderAdminCodMethod(), $order->grand_total, OrderPaymentStatus::Paid);
        expect($paid->paid_at)->not->toBeNull();
    });

    it('does not auto-confirm a fully paid order by default', function (): void {
        config(['orders.auto_confirm_on_full_payment' => false]);
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);

        $orders->recordPayment($order, orderAdminCodMethod(), $order->grand_total, OrderPaymentStatus::Paid);

        expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    });

    it('auto-confirms a pending order when the final payment lands and the config is enabled', function (): void {
        config(['orders.auto_confirm_on_full_payment' => true]);
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);

        $orders->recordPayment($order, orderAdminCodMethod(), $order->grand_total, OrderPaymentStatus::Paid);

        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
    });

    it('corrects the billing address snapshot and logs an event', function (): void {
        [$order] = orderAdminMakePendingOrder(1);

        app(OrderService::class)->updateOrderBillingAddress($order, [
            'recipient_name' => 'Billing Contact',
            'address_line_1' => 'Road 1',
            'city' => 'Dhaka',
        ]);

        $fresh = $order->fresh();
        expect($fresh->billing_address_snapshot['recipient_name'])->toBe('Billing Contact');
        expect($fresh->events()->where('type', 'address_updated')->first()->metadata['field'])->toBe('billing_address_snapshot');
    });
});

describe('ORDER 1C — cancellation, restock, and serial safety', function (): void {
    it('requires a reason to cancel an order', function (): void {
        [$order] = orderAdminMakePendingOrder(1);

        expect(fn () => app(OrderService::class)->cancelOrder($order, '  '))
            ->toThrow(InvalidArgumentException::class);
        expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    });

    it('cancelling a pending order releases its reservations', function (): void {
        [$order, $variant] = orderAdminMakePendingOrder(2);

        app(OrderService::class)->cancelOrder($order, 'Customer request');

        expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
        expect(StockItem::query()->where('product_variant_id', $variant->id)->value('reserved_quantity'))->toBe(0);
    });

    it('restocks committed stock when a confirmed order is cancelled', function (): void {
        [$order, $variant] = orderAdminMakePendingOrder(2);
        $orders = app(OrderService::class);
        $orders->updateStatus($order, OrderStatus::Confirmed);

        $committed = StockItem::query()->where('product_variant_id', $variant->id)->first();
        expect($committed->quantity)->toBe(8);
        expect($committed->reserved_quantity)->toBe(0);

        $orders->cancelOrder($order, 'Stock issue');

        $after = StockItem::query()->where('product_variant_id', $variant->id)->first();
        expect($after->quantity)->toBe(10);
        expect($after->reserved_quantity)->toBe(0);
        expect(StockMovement::query()->where('product_variant_id', $variant->id)->where('type', 'return')->count())->toBe(1);
    });

    it('returns sold serials to available when a committed serialized order is cancelled', function (): void {
        [$order] = orderAdminMakeSerializedOrder(2, 3);
        $orders = app(OrderService::class);
        $orders->updateStatus($order, OrderStatus::Confirmed);

        expect(SerialNumber::query()->where('status', 'sold')->count())->toBe(2);

        $orders->cancelOrder($order, 'Return to stock');

        expect(SerialNumber::query()->where('status', 'sold')->count())->toBe(0);
        expect(SerialNumber::query()->where('status', 'available')->count())->toBe(3);
    });

    it('fails loudly when fewer sold serials exist than the cancelled quantity', function (): void {
        [$order] = orderAdminMakeSerializedOrder(2, 2);
        $orders = app(OrderService::class);
        $orders->updateStatus($order, OrderStatus::Confirmed);

        SerialNumber::query()->where('status', 'sold')->first()->update(['status' => 'returned']);

        expect(fn () => $orders->cancelOrder($order, 'Manual return'))
            ->toThrow(InsufficientStockException::class);
        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
    });

    it('blocks illegal cancellations from shipped', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);
        $orders->updateStatus($order, OrderStatus::Confirmed);
        $orders->updateStatus($order, OrderStatus::Processing);
        $orders->updateStatus($order, OrderStatus::Shipped);

        expect(fn () => $orders->cancelOrder($order, 'Too late'))
            ->toThrow(InvalidOrderTransitionException::class);
    });

    it('flags a refund requirement on a paid cancellation without silently refunding', function (): void {
        [$order] = orderAdminMakePendingOrder(1);
        $orders = app(OrderService::class);
        $orders->recordPayment($order, orderAdminCodMethod(), $order->grand_total, OrderPaymentStatus::Paid);

        $orders->cancelOrder($order, 'Full refund due');

        $fresh = $order->fresh();
        expect($fresh->status)->toBe(OrderStatus::Cancelled);
        expect($fresh->payments()->count())->toBe(1);
        expect($fresh->payments()->first()->status)->toBe(OrderPaymentStatus::Paid);
        expect($fresh->payments()->first()->amount)->toBe($fresh->grand_total);

        $flag = $fresh->events()->where('type', 'financial_adjustment_required')->first();
        expect($flag)->not->toBeNull();
        expect($flag->description)->toContain('Refund required');
    });
});
