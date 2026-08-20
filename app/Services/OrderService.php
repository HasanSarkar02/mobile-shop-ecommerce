<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderEventType;
use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Events\OrderPaymentRecorded;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Exceptions\CartAlreadyConvertedException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\ReservationLimitExceededException;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Support\DatabaseLockRetry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SequenceGenerator $sequences,
        private readonly CouponService $coupons,
    ) {}

    /**
     * @param  array{shipping_address?: array, billing_address?: array, shipping_address_id?: int, billing_address_id?: int, shipping_method_id?: int, payment_method_id?: int, customer_note?: string, guest_name?: string, guest_email?: string, guest_phone?: string, shipping_cost?: int, tax_total?: int}  $orderData
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

        return DatabaseLockRetry::run(function () use ($cart, $orderData, $source): Order {
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

            $cart->load('items');

            if ($cart->items->isEmpty()) {
                throw new \RuntimeException('Cannot create an order from an empty cart.');
            }

            // Reservation abuse control: bound how many active Pending orders a
            // single identity (authenticated customer_id, or guest_email for
            // guests) may hold at once per tenant. The count ignores
            // Cancelled/Confirmed/Expired orders, and the unique
            // (tenant_id, active_reservation_key) index is the race-safe
            // backstop for two concurrent checkouts sharing an identity.
            $customerId = $cart->customer_id;
            $guestEmail = $customerId
                ? null
                : strtolower(trim((string) ($orderData['guest_email'] ?? '')));

            $reservationKey = $customerId
                ? "customer:{$customerId}"
                : "guest:{$guestEmail}";

            $identityScope = function ($query) use ($customerId, $guestEmail): void {
                if ($customerId) {
                    $query->where('customer_id', $customerId);
                } else {
                    $query->whereRaw('LOWER(guest_email) = ?', [$guestEmail]);
                }
            };

            // Release this identity's expired reservations first so an expired
            // but not-yet-released order no longer counts toward the limit.
            foreach (Order::query()
                ->where('status', OrderStatus::Pending)
                ->where('reservation_expires_at', '<', now())
                ->where($identityScope)
                ->pluck('id') as $expiredId) {
                $this->claimPendingOrder($expiredId, 'Auto-cancelled — reservation expired.');
            }

            $activePendingCount = Order::query()
                ->where('status', OrderStatus::Pending)
                ->where($identityScope)
                ->where(fn ($query) => $query
                    ->whereNull('reservation_expires_at')
                    ->orWhere('reservation_expires_at', '>', now()))
                ->count();

            if ($activePendingCount >= (int) config('orders.max_pending_orders_per_identity', 1)) {
                throw new ReservationLimitExceededException(
                    'Your existing pending order already holds this product. Please complete or cancel that order before creating another.'
                );
            }

            // Lock every authoritative variant in a deterministic order before
            // validating prices or creating any order records. This makes the
            // locked variant price the checkout decision point.
            $variantIds = $cart->items
                ->pluck('product_variant_id')
                ->unique()
                ->sort()
                ->values();
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($variants->count() !== $variantIds->count()) {
                throw new InvalidOrderStateException('A cart item is no longer available in this store.');
            }

            $variants->load('product');

            // Lock every stock row this checkout will touch in ascending
            // product_variant_id order before validating prices or creating any
            // records. Combined with the ascending variant locks above, all
            // multi-row inventory locks for a checkout follow the same
            // deterministic order regardless of cart item order.
            $this->inventory->lockStockItemsForVariants($variants);

            foreach ($cart->items as $item) {
                $variant = $variants->get($item->product_variant_id);

                if ((int) $item->unit_price !== (int) $variant->price) {
                    throw new InvalidOrderStateException("The price of '{$variant->sku}' has changed. Refresh your cart before placing the order.");
                }

                if (! $this->inventory->isPurchasable($variant, $item->quantity)) {
                    throw new InvalidOrderStateException("'{$variant->sku}' is no longer available in the requested quantity.");
                }
            }

            $shippingCost = $orderData['shipping_cost'] ?? 0;

            $couponResult = $this->coupons->lockAndComputeForCart($cart, $cart->customer);
            $discountTotal = $couponResult->valid ? $couponResult->discountAmount : 0;

            if ($couponResult->valid && $couponResult->freeShipping) {
                $shippingCost = 0;
            }

            try {
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
                    'shipping_cost' => $shippingCost,
                    'discount_total' => $discountTotal,
                    'tax_total' => $orderData['tax_total'] ?? 0,
                    'shipping_address_id' => $orderData['shipping_address_id'] ?? null,
                    'shipping_address_snapshot' => $orderData['shipping_address'] ?? null,
                    'billing_address_id' => $orderData['billing_address_id'] ?? null,
                    'billing_address_snapshot' => $orderData['billing_address'] ?? null,
                    'customer_note' => $orderData['customer_note'] ?? null,
                    'reservation_expires_at' => now()->addHours((int) config('orders.reservation_hours')),
                    'active_reservation_key' => $reservationKey,
                    'placed_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // A concurrent checkout for the same identity won the
                // (tenant_id, active_reservation_key) race first — treat it as
                // the same limit being reached.
                if (str_contains($e->getMessage(), 'active_reservation_key')) {
                    throw new ReservationLimitExceededException(
                        'Your existing pending order already holds this product. Please complete or cancel that order before creating another.'
                    );
                }

                throw $e;
            }

            foreach ($cart->items as $item) {
                $variant = $variants->get($item->product_variant_id);

                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name_snapshot' => $variant->product->name ?? $variant->sku,
                    'variant_sku_snapshot' => $variant->sku,
                    'unit_price' => (int) $variant->price,
                    'quantity' => $item->quantity,
                    'line_total' => (int) $variant->price * $item->quantity,
                ]);

                $this->inventory->reserve($variant, $item->quantity, null, $order);
            }

            // Authoritative totals single path — subtotal and grand_total are
            // derived here from the line items, never set independently.
            $this->recalculateTotals($order);

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
        DatabaseLockRetry::run(function () use ($order, $newStatus, $note): void {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStatusTransition($lockedOrder, $newStatus);
            $this->applyStatusTransition($lockedOrder, $newStatus, $note);
        });
    }

    private function assertStatusTransition(Order $order, OrderStatus $newStatus): void
    {
        if (! in_array($newStatus, $order->status->allowedNextStatuses(), true)) {
            throw new InvalidOrderTransitionException(
                "Cannot move order from {$order->status->label()} to {$newStatus->label()}.",
            );
        }
    }

    private function applyStatusTransition(Order $order, OrderStatus $newStatus, ?string $note = null): void
    {
        $from = $order->status;
        $returnedSerials = [];

        $order->update([
            'status' => $newStatus,
            'active_reservation_key' => $newStatus === OrderStatus::Pending ? $order->active_reservation_key : null,
        ]);

        if ($newStatus === OrderStatus::Confirmed) {
            $this->inventory->lockStockItemsForVariants($order->items->pluck('variant')->filter());

            foreach ($order->items as $item) {
                if ($item->variant) {
                    $this->inventory->commit($item->variant, $item->quantity, null, $order, $item);
                }
            }
        }

        if ($newStatus === OrderStatus::Cancelled && in_array($from, [OrderStatus::Pending], true)) {
            $this->inventory->lockStockItemsForVariants($order->items->pluck('variant')->filter());

            foreach ($order->items as $item) {
                if ($item->variant) {
                    $this->inventory->release($item->variant, $item->quantity, null, $order);
                }
            }
        }

        if ($newStatus === OrderStatus::Cancelled && in_array($from, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped], true)) {
            $this->inventory->lockStockItemsForVariants($order->items->pluck('variant')->filter());

            foreach ($order->items as $item) {
                if ($item->variant) {
                    $returned = $this->inventory->restockFromCancellation($item->variant, $item->quantity, null, $order, $item);
                    if ($returned->isNotEmpty()) {
                        $returnedSerials[$item->variant_sku_snapshot] = $returned->pluck('imei_or_serial')->all();
                    }
                }
            }
        }

        if ($newStatus === OrderStatus::Cancelled) {
            $this->coupons->releaseForOrder($order);
        }

        if ($newStatus === OrderStatus::Cancelled && $this->amountPaid($order) > 0) {
            $this->logEvent(
                $order,
                OrderEventType::FinancialAdjustmentRequired,
                'Refund required: this cancelled order already has '.number_format($this->amountPaid($order) / 100, 2).' paid. A refund must be issued — refund tooling arrives in a later phase.'
            );
        }

        $this->logEvent(
            $order,
            OrderEventType::StatusChanged,
            $note ?? "Status changed from {$from->label()} to {$newStatus->label()}.",
            $from,
            $newStatus,
            $returnedSerials !== [] ? ['returned_serials' => $returnedSerials] : null,
        );

        OrderStatusChanged::dispatch($order, $from, $newStatus);

        if ($newStatus === OrderStatus::Cancelled) {
            OrderCancelled::dispatch($order);
        }
    }

    public function recordPayment(Order $order, ?PaymentMethod $method, int $amount, OrderPaymentStatus $status, ?string $reference = null): OrderPayment
    {
        return DB::transaction(function () use ($order, $method, $amount, $status, $reference): OrderPayment {
            if ($amount <= 0) {
                throw new InvalidOrderStateException('Payment amount must be greater than zero.');
            }

            if ($order->status === OrderStatus::Cancelled) {
                throw new InvalidOrderStateException('Payments cannot be recorded on a cancelled order.');
            }

            $paidAlready = $this->amountPaid($order);

            if ($status === OrderPaymentStatus::Paid) {
                $remainingDue = (int) $order->grand_total - $paidAlready;

                if ($remainingDue <= 0) {
                    throw new InvalidOrderStateException('This order is already fully paid — no amount is due.');
                }

                if ($amount > $remainingDue) {
                    throw new InvalidOrderStateException(
                        'Payment of '.number_format($amount / 100, 2).' exceeds the remaining due of '.number_format($remainingDue / 100, 2).'.'
                    );
                }
            }

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

            if ($status === OrderPaymentStatus::Paid
                && $order->status === OrderStatus::Pending
                && config('orders.auto_confirm_on_full_payment')
                && ($paidAlready + $amount) >= (int) $order->grand_total
            ) {
                $this->updateStatus($order, OrderStatus::Confirmed, 'Confirmed — order fully paid.');
            }

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

    /**
     * Correct the order's own contact record without touching the customer
     * profile. Only the order-level guest contact columns are updated.
     *
     * @param  Order  $order  an order already scoped to the current tenant
     */
    public function updateOrderContact(Order $order, string $name, ?string $email, ?string $phone): void
    {
        DB::transaction(function () use ($order, $name, $email, $phone): void {
            $before = [
                'name' => $order->guest_name,
                'email' => $order->guest_email,
                'phone' => $order->guest_phone,
            ];

            $order->update([
                'guest_name' => $name,
                'guest_email' => $email,
                'guest_phone' => $phone,
            ]);

            $this->logEvent(
                $order,
                OrderEventType::ContactUpdated,
                'Order contact corrected.',
                metadata: ['before' => $before, 'after' => ['name' => $name, 'email' => $email, 'phone' => $phone]],
            );
        }, 3);
    }

    /**
     * Correct the order's shipping address snapshot. The historical snapshot is
     * replaced with the corrected array so the current order record reflects the
     * accurate address. The customer's master Address record and
     * shipping_address_id are deliberately left untouched.
     *
     * @param  Order  $order  an order already scoped to the current tenant
     * @param  array{recipient_name: string, phone?: string, address_line_1: string, address_line_2?: string, city: string, area?: string, postal_code?: string, country?: string}  $address
     */
    public function updateOrderShippingAddress(Order $order, array $address): void
    {
        DB::transaction(function () use ($order, $address): void {
            $before = $order->shipping_address_snapshot ?? [];

            $order->update(['shipping_address_snapshot' => $address]);

            $this->logEvent(
                $order,
                OrderEventType::AddressUpdated,
                'Shipping address corrected.',
                metadata: ['field' => 'shipping_address_snapshot', 'before' => $before, 'after' => $address],
            );
        }, 3);
    }

    public function amountPaid(Order $order): int
    {
        return (int) $order->payments()->where('status', OrderPaymentStatus::Paid)->sum('amount');
    }

    /**
     * Authoritative totals path. Subtotal is always derived from the line
     * items; grand_total = subtotal + shipping + tax - discount. Refuses to
     * persist a state that is impossible or already overpaid — overpaid
     * totals cannot be silently reduced because refunds are not yet supported.
     */
    public function recalculateTotals(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $subtotal = (int) $order->items()->sum('line_total');
            $discount = (int) $order->discount_total;
            $shipping = (int) $order->shipping_cost;
            $tax = (int) $order->tax_total;

            if ($discount > $subtotal) {
                throw new InvalidOrderStateException(
                    'Discount of '.number_format($discount / 100, 2).' exceeds the current item subtotal of '.number_format($subtotal / 100, 2).'. Adjust or remove the discount first.'
                );
            }

            $grandTotal = $subtotal + $shipping + $tax - $discount;

            $paid = $this->amountPaid($order);

            if ($grandTotal < $paid) {
                throw new InvalidOrderStateException(
                    'Cannot lower the order total below the '.number_format($paid / 100, 2).' already paid. Refunds are not yet supported — keep the total at or above the paid amount.'
                );
            }

            $order->update(['subtotal' => $subtotal, 'grand_total' => $grandTotal]);
        }, 3);
    }

    private function assertPendingEditable(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidOrderStateException('Order line items and totals can only be edited while the order is Pending.');
        }
    }

    private function assertItemBelongsToOrder(Order $order, OrderItem $item): void
    {
        if ((int) $item->order_id !== (int) $order->id) {
            throw new InvalidOrderStateException('Order item does not belong to this order.');
        }
    }

    public function addItem(Order $order, ProductVariant $variant, int $quantity): OrderItem
    {
        $this->assertPendingEditable($order);

        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        return DB::transaction(function () use ($order, $variant, $quantity): OrderItem {
            $this->inventory->reserve($variant, $quantity, null, $order);

            $item = $order->items()->create([
                'tenant_id' => $order->tenant_id,
                'product_variant_id' => $variant->id,
                'product_name_snapshot' => $variant->product?->name ?? $variant->sku,
                'variant_sku_snapshot' => $variant->sku,
                'unit_price' => (int) $variant->price,
                'quantity' => $quantity,
                'line_total' => (int) $variant->price * $quantity,
            ]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::ItemAdded,
                'Added '.$quantity.' × '.$variant->sku.' at '.number_format((int) $variant->price / 100, 2).' each.',
                metadata: ['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'quantity' => $quantity, 'unit_price' => (int) $variant->price],
            );

            return $item;
        }, 3);
    }

    public function updateItemQuantity(Order $order, OrderItem $item, int $quantity): void
    {
        $this->assertPendingEditable($order);
        $this->assertItemBelongsToOrder($order, $item);

        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $beforeQuantity = $item->quantity;

        if ($quantity === $beforeQuantity) {
            return;
        }

        $variant = $item->variant;

        if ($variant === null) {
            throw new InvalidOrderStateException('Item has no variant and cannot be edited.');
        }

        $delta = $quantity - $beforeQuantity;

        DB::transaction(function () use ($order, $item, $variant, $quantity, $delta, $beforeQuantity): void {
            if ($delta > 0) {
                $this->inventory->reserve($variant, $delta, null, $order);
            } else {
                $this->inventory->release($variant, -$delta, null, $order);
            }

            $item->update([
                'quantity' => $quantity,
                'line_total' => $item->unit_price * $quantity,
            ]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::ItemUpdated,
                "Quantity for {$variant->sku} updated from {$beforeQuantity} to {$quantity}.",
                metadata: ['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'before_quantity' => $beforeQuantity, 'after_quantity' => $quantity],
            );
        }, 3);
    }

    public function removeItem(Order $order, OrderItem $item, ?string $reason = null): void
    {
        $this->assertPendingEditable($order);
        $this->assertItemBelongsToOrder($order, $item);

        $variant = $item->variant;
        $sku = $item->variant_sku_snapshot;
        $quantity = $item->quantity;
        $unitPrice = $item->unit_price;

        DB::transaction(function () use ($order, $item, $variant, $sku, $quantity, $unitPrice, $reason): void {
            if ($variant) {
                $this->inventory->release($variant, $quantity, null, $order);
            }

            $item->delete();

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::ItemRemoved,
                'Removed '.$quantity.' × '.$sku.' ('.number_format($unitPrice / 100, 2).' each).',
                metadata: ['sku' => $sku, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'reason' => $reason],
            );
        }, 3);
    }

    public function changeItemVariant(Order $order, OrderItem $item, ProductVariant $newVariant): void
    {
        $this->assertPendingEditable($order);
        $this->assertItemBelongsToOrder($order, $item);

        $oldVariant = $item->variant;

        if ($oldVariant?->id === $newVariant->id) {
            return;
        }

        $quantity = $item->quantity;

        DatabaseLockRetry::run(function () use ($order, $item, $oldVariant, $newVariant, $quantity): void {
            // Lock both stock rows (old and new variant) in ascending variant
            // order up front, then mutate in the original business order
            // (release old before reserving new). Lock order is deterministic
            // regardless of which variant is being swapped in which direction.
            $this->inventory->lockStockItemsForVariants(collect([$oldVariant, $newVariant])->filter());

            if ($oldVariant) {
                $this->inventory->release($oldVariant, $quantity, null, $order);
            }

            $this->inventory->reserve($newVariant, $quantity, null, $order);

            $item->update([
                'product_variant_id' => $newVariant->id,
                'product_name_snapshot' => $newVariant->product?->name ?? $newVariant->sku,
                'variant_sku_snapshot' => $newVariant->sku,
                'unit_price' => (int) $newVariant->price,
                'line_total' => (int) $newVariant->price * $quantity,
            ]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::ItemUpdated,
                "Item swapped from {$oldVariant?->sku} to {$newVariant->sku}.",
                metadata: ['before_sku' => $oldVariant?->sku, 'after_sku' => $newVariant->sku, 'quantity' => $quantity],
            );
        });
    }

    public function adjustItemUnitPrice(Order $order, OrderItem $item, int $unitPrice, string $reason): void
    {
        $this->assertPendingEditable($order);
        $this->assertItemBelongsToOrder($order, $item);

        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for a unit price adjustment.');
        }

        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('Unit price cannot be negative.');
        }

        $before = $item->unit_price;

        if ($before === $unitPrice) {
            return;
        }

        $sku = $item->variant_sku_snapshot;

        DB::transaction(function () use ($order, $item, $unitPrice, $reason, $before, $sku): void {
            $item->update([
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $item->quantity,
            ]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::PriceAdjusted,
                "Unit price for {$sku} adjusted from ".number_format($before / 100, 2).' to '.number_format($unitPrice / 100, 2).'.',
                metadata: ['sku' => $sku, 'before_unit_price' => $before, 'after_unit_price' => $unitPrice, 'reason' => $reason],
            );
        }, 3);
    }

    public function applyOrderDiscount(Order $order, int $discount, string $reason): void
    {
        $this->assertPendingEditable($order);

        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for an order discount.');
        }

        if ($discount < 0) {
            throw new \InvalidArgumentException('Discount cannot be negative.');
        }

        $before = (int) $order->discount_total;

        DB::transaction(function () use ($order, $discount, $reason, $before): void {
            $order->update(['discount_total' => $discount]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::DiscountAdjusted,
                'Order discount set to '.number_format($discount / 100, 2).'.',
                metadata: ['before_discount' => $before, 'after_discount' => $discount, 'reason' => $reason],
            );
        }, 3);
    }

    public function updateShipping(Order $order, int $shippingCost, ?int $shippingMethodId = null, ?string $reason = null): void
    {
        $this->assertPendingEditable($order);

        if ($shippingCost < 0) {
            throw new \InvalidArgumentException('Shipping cost cannot be negative.');
        }

        $beforeCost = (int) $order->shipping_cost;
        $beforeMethodId = $order->shipping_method_id;

        DB::transaction(function () use ($order, $shippingCost, $shippingMethodId, $reason, $beforeCost, $beforeMethodId): void {
            $order->update([
                'shipping_cost' => $shippingCost,
                'shipping_method_id' => $shippingMethodId ?? $order->shipping_method_id,
            ]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::ShippingUpdated,
                'Shipping updated.',
                metadata: ['before_cost' => $beforeCost, 'after_cost' => $shippingCost, 'before_method_id' => $beforeMethodId, 'after_method_id' => $shippingMethodId, 'reason' => $reason],
            );
        }, 3);
    }

    /**
     * Correct the order's billing address snapshot without touching the
     * customer's master Address record.
     *
     * @param  Order  $order  an order already scoped to the current tenant
     * @param  array{recipient_name: string, phone?: string, address_line_1: string, address_line_2?: string, city: string, area?: string, postal_code?: string, country?: string}  $address
     */
    public function updateOrderBillingAddress(Order $order, array $address): void
    {
        DB::transaction(function () use ($order, $address): void {
            $before = $order->billing_address_snapshot ?? [];

            $order->update(['billing_address_snapshot' => $address]);

            $this->logEvent(
                $order,
                OrderEventType::AddressUpdated,
                'Billing address corrected.',
                metadata: ['field' => 'billing_address_snapshot', 'before' => $before, 'after' => $address],
            );
        }, 3);
    }

    public function cancelOrder(Order $order, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required to cancel an order.');
        }

        $this->updateStatus($order, OrderStatus::Cancelled, $reason);
    }

    private function logEvent(
        Order $order,
        OrderEventType $type,
        string $description,
        ?OrderStatus $from = null,
        ?OrderStatus $to = null,
        ?array $metadata = null,
    ): void {
        $order->events()->create([
            'tenant_id' => $order->tenant_id,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'description' => $description,
            'metadata' => $metadata,
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

        DatabaseLockRetry::run(function () use ($query, &$released): void {
            foreach ($query->pluck('id') as $orderId) {
                if ($this->claimPendingOrder(
                    $orderId,
                    'Auto-cancelled — reservation expired.',
                    fn (Order $order): bool => $order->reservation_expires_at?->isPast() === true,
                )) {
                    $released++;
                }
            }
        });

        return $released;
    }

    /**
     * Atomically cancel a Pending order and release its reservation exactly
     * once. No-op (false) when the order is no longer Pending, so repeated
     * payment fail/cancel callbacks are harmless.
     */
    public function cancelPendingOrderReservation(Order $order, string $note): bool
    {
        return DatabaseLockRetry::run(fn () => $this->claimPendingOrder($order->id, $note));
    }

    private function claimPendingOrder(int $orderId, string $note, ?callable $eligible = null): bool
    {
        return DB::transaction(function () use ($orderId, $note, $eligible): bool {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if ($order === null || $order->status !== OrderStatus::Pending) {
                return false;
            }

            if ($eligible !== null && ! $eligible($order)) {
                return false;
            }

            $this->assertStatusTransition($order, OrderStatus::Cancelled);
            $this->applyStatusTransition($order, OrderStatus::Cancelled, $note);

            return true;
        });
    }
}
