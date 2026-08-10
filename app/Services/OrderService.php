<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderEventType;
use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Exceptions\CartAlreadyConvertedException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use App\Events\OrderCancelled;
use App\Events\OrderPaymentRecorded;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\OrderItem;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SequenceGenerator $sequences,
        private readonly CouponService $coupons,
    ) {
    }

    /**
     * @param array{shipping_address?: array, billing_address?: array, shipping_address_id?: int, billing_address_id?: int, shipping_method_id?: int, payment_method_id?: int, customer_note?: string} $orderData
     */
    public function createFromCart(
        Cart $cart,
        array $orderData,
        OrderSource $source = OrderSource::Website,
        ?Customer $guestOverride = null,
    ): Order {
        if (! $cart->customer_id
                && (empty($orderData['guest_name']) || empty($orderData['guest_email']) || empty($orderData['guest_phone']))
            ) {
                throw new \InvalidArgumentException('Guest checkout requires guest_name, guest_email, and guest_phone.');
            }
        return DB::transaction(function () use ($cart, $orderData, $source): Order {
            // Lock the cart row for the remainder of this transaction so a
            // concurrent/duplicate checkout submission for the same cart blocks
            // here until this transaction commits or rolls back, then re-checks
            // converted_at and finds it already set. This is the authoritative
            // guard against double order creation; UI-level submit guards are
            // only a UX nicety on top of this.
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            if ($cart->converted_at !== null) {
                throw new CartAlreadyConvertedException('This cart has already been converted into an order.');
            }

            $cart->load('items.variant');

            if ($cart->items->isEmpty()) {
                throw new \RuntimeException('Cannot create an order from an empty cart.');
            }

            $subtotal = $cart->items->sum(fn ($item) => $item->lineTotal());
            $shippingCost = $orderData['shipping_cost'] ?? 0;

            $couponResult = $this->coupons->lockAndComputeForCart($cart, $cart->customer);
            $discountTotal = $couponResult->valid ? $couponResult->discountAmount : 0;

            if ($couponResult->valid && $couponResult->freeShipping) {
                $shippingCost = 0;
            }

            $order = Order::query()->create([
                'tenant_id' => $cart->tenant_id,
                'order_number' => $this->sequences->nextFormatted($cart->tenant_id, 'order_number', 'ORD'),
                'invoice_number' => $this->sequences->nextFormatted($cart->tenant_id, 'invoice_number', 'INV'),
                'customer_id' => $cart->customer_id,
                'guest_name' => $orderData['guest_name'] ?? null,
                'guest_email' => $orderData['guest_email'] ?? null,
                'guest_phone' => $orderData['guest_phone'] ?? null,
                'status' => OrderStatus::Pending,
                'order_source' => $source,
                'sales_channel' => 'online_store',
                'payment_method_id' => $orderData['payment_method_id'] ?? null,
                'shipping_method_id' => $orderData['shipping_method_id'] ?? null,
                'currency_code' => $cart->currency_code,
                'currency_rate' => 1.000000,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount_total' => $discountTotal,
                'tax_total' => $orderData['tax_total'] ?? 0,
                'grand_total' => $subtotal + $shippingCost + ($orderData['tax_total'] ?? 0) - $discountTotal,
                'shipping_address_id' => $orderData['shipping_address_id'] ?? null,
                'shipping_address_snapshot' => $orderData['shipping_address'] ?? null,
                'billing_address_id' => $orderData['billing_address_id'] ?? null,
                'billing_address_snapshot' => $orderData['billing_address'] ?? null,
                'customer_note' => $orderData['customer_note'] ?? null,
                'reservation_expires_at' => now()->addHours((int) config('orders.reservation_hours')),
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name_snapshot' => $item->variant->product->name ?? $item->variant->sku,
                    'variant_sku_snapshot' => $item->variant->sku,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                ]);

                $this->inventory->reserve($item->variant, $item->quantity, null, $order);
            }

            $order->fulfillments()->create([
                'tenant_id' => $order->tenant_id,
                'status' => OrderFulfillmentStatus::Pending,
            ]);

            $status = OrderStatus::Pending->label();

            $this->logEvent(
                $order,
                OrderEventType::StatusChanged,
                "Order placed as {$status}.",
                null,
                OrderStatus::Pending,
            );

            $cart->update(['converted_at' => now()]);
            $this->coupons->recordRedemption($order, $cart, $cart->customer, $discountTotal);
            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    public function updateStatus(Order $order, OrderStatus $newStatus, ?string $note = null): void
    {
        if (! in_array($newStatus, $order->status->allowedNextStatuses(), true)) {
            throw new InvalidOrderTransitionException(
                "Cannot move order from {$order->status->label()} to {$newStatus->label()}.",
            );
        }

        DB::transaction(function () use ($order, $newStatus, $note): void {
            $from = $order->status;

            $order->update(['status' => $newStatus]);

            if ($newStatus === OrderStatus::Confirmed) {
                foreach ($order->items as $item) {
                    if ($item->variant) {
                        $this->inventory->commit($item->variant, $item->quantity, null, $order);
                    }
                }
            }

            if ($newStatus === OrderStatus::Cancelled && in_array($from, [OrderStatus::Pending], true)) {
                foreach ($order->items as $item) {
                    if ($item->variant) {
                        $this->inventory->release($item->variant, $item->quantity, null, $order);
                    }
                }
            }

            if ($newStatus === OrderStatus::Cancelled) {
                $this->coupons->releaseForOrder($order);
            }

            // Cancelling after stock has already been committed (Confirmed/Processing/Shipped) requires
            // a restock-on-cancellation path, which belongs to the future Returns/RMA module — not built here.

            $this->logEvent(
                $order,
                OrderEventType::StatusChanged,
                $note ?? "Status changed from {$from->label()} to {$newStatus->label()}.",
                $from,
                $newStatus,
            );

            OrderStatusChanged::dispatch($order, $from, $newStatus);

            if ($newStatus === OrderStatus::Cancelled) {
                OrderCancelled::dispatch($order);
            }
        }, 3);
    }

    public function recordPayment(Order $order, ?PaymentMethod $method, int $amount, OrderPaymentStatus $status, ?string $reference = null): OrderPayment
    {
        return DB::transaction(function () use ($order, $method, $amount, $status, $reference): OrderPayment {
            $payment = $order->payments()->create([
                'tenant_id' => $order->tenant_id,
                'payment_method_id' => $method?->id,
                'amount' => $amount,
                'status' => $status,
                'transaction_reference' => $reference,
                'paid_at' => $status === OrderPaymentStatus::Paid ? now() : null,
            ]);

            $this->logEvent(
                $order,
                OrderEventType::PaymentRecorded,
                'Payment of '.number_format($amount / 100, 2)." recorded as {$status->label()}.",
            );

            OrderPaymentRecorded::dispatch($payment);

            return $payment;
        }, 3);
    }

    public function updateFulfillment(OrderFulfillment $fulfillment, OrderFulfillmentStatus $status, ?string $trackingNumber = null, ?string $courierName = null): void
    {
        DB::transaction(function () use ($fulfillment, $status, $trackingNumber, $courierName): void {
            $fulfillment->update([
                'status' => $status,
                'tracking_number' => $trackingNumber ?? $fulfillment->tracking_number,
                'courier_name' => $courierName ?? $fulfillment->courier_name,
                'shipped_at' => $status === OrderFulfillmentStatus::Shipped ? now() : $fulfillment->shipped_at,
                'delivered_at' => $status === OrderFulfillmentStatus::Delivered ? now() : $fulfillment->delivered_at,
            ]);

            $this->logEvent($fulfillment->order, OrderEventType::FulfillmentUpdated, "Fulfillment marked as {$status->label()}.");
        }, 3);
    }

    public function addInternalNote(Order $order, string $note): void
    {
        $order->update(['internal_note' => $note]);

        $this->logEvent($order, OrderEventType::NoteAdded, $note);
    }

    private function logEvent(
        Order $order,
        OrderEventType $type,
        string $description,
        ?OrderStatus $from = null,
        ?OrderStatus $to = null,
    ): void {
        $order->events()->create([
            'tenant_id' => $order->tenant_id,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }
    public function releaseExpiredReservations(?ProductVariant $onlyVariant = null): int
    {
        $query = Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<', now());

        if ($onlyVariant) {
            $query->whereHas('items', fn ($q) => $q->where('product_variant_id', $onlyVariant->id));
        }

        $released = 0;

        foreach ($query->get() as $order) {
            $this->updateStatus($order, OrderStatus::Cancelled, 'Auto-cancelled — reservation expired.');
            $released++;
        }

        return $released;
    }
}