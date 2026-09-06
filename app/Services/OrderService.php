<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FulfillmentStrategy;
use App\Enums\OrderEventType;
use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\ShippingMethodType;
use App\Events\OrderCancelled;
use App\Events\OrderPaymentRecorded;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Exceptions\CartAlreadyConvertedException;
use App\Exceptions\InsufficientStockException;
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
use App\Models\ShippingMethod;
use App\Services\Pricing\CartPricingService;
use App\Support\DatabaseLockRetry;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SequenceGenerator $sequences,
        private readonly CouponService $coupons,
        private readonly CartPricingService $pricing,
    ) {}

    /**
     * @param  array{shipping_address?: array, billing_address?: array, shipping_address_id?: int, billing_address_id?: int, shipping_method_id?: int, payment_method_id?: int, customer_note?: string, guest_name?: string, guest_email?: string, guest_phone?: string, shipping_cost?: int, tax_total?: int, preorder_ack_at?: CarbonInterface|string|null}  $orderData
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

                $expectedUnitPrice = $this->pricing->resolveUnitPrice($variant);
                if ((int) $item->unit_price !== $expectedUnitPrice) {
                    throw new InvalidOrderStateException("The price of '{$variant->sku}' has changed. Refresh your cart before placing the order.");
                }

                if (! $this->inventory->isPurchasable($variant, $item->quantity)) {
                    throw new InsufficientStockException("'{$variant->sku}' is no longer available in the requested quantity.");
                }
            }

            // Unified priority: 1.Method Free/Pickup ->0, 2.Coupon FreeShipping (incl. automatic blank-code) ->0, 3.Geo free_threshold (post-discount) ->0, 4.Geo charge
            $couponResult = $this->coupons->lockAndComputeForCart($cart, $cart->customer);
            $discountTotal = $couponResult->valid ? $couponResult->discountAmount : 0;

            $cartSubtotalForShipping = $cart->items->sum(fn ($item) => $item->lineTotal());
            $subtotalAfterDiscountForShipping = max(0, $cartSubtotalForShipping - $discountTotal);

            $methodForCheck = null;
            $isMethodFree = false;
            if (! empty($orderData['shipping_method_id'])) {
                $methodForCheck = ShippingMethod::query()->find($orderData['shipping_method_id']);
                $isMethodFree = $methodForCheck !== null && ($methodForCheck->type === ShippingMethodType::Free || $methodForCheck->type === ShippingMethodType::Pickup);
            }
            $isCouponFree = $couponResult->valid && $couponResult->freeShipping;

            if ($isMethodFree) {
                $shippingCost = 0;
                $freeShippingReason = $methodForCheck->type === ShippingMethodType::Pickup ? 'pickup' : 'method_free';
            } elseif ($isCouponFree) {
                $shippingCost = 0;
                $freeShippingReason = $couponResult->coupon && empty($couponResult->coupon->code) ? 'coupon_automatic' : 'coupon';
            } else {
                // Authoritative geo re-quote (protects against tampered orderData[shipping_cost] and ensures preview==persistence)
                $shippingService = app(ShippingService::class);
                $addr = $orderData['shipping_address'] ?? null;
                $upazilaId = isset($addr['bd_upazila_id']) && $addr['bd_upazila_id'] !== '' ? (int) $addr['bd_upazila_id'] : null;
                $districtId = isset($addr['bd_district_id']) && $addr['bd_district_id'] !== '' ? (int) $addr['bd_district_id'] : null;
                $divisionId = isset($addr['bd_division_id']) && $addr['bd_division_id'] !== '' ? (int) $addr['bd_division_id'] : null;
                // Single matched-rate query — zone name for snapshot + free-check; no redundant query.
                $matchedRate = ($upazilaId !== null || $districtId !== null || $divisionId !== null)
                    ? $shippingService->getMatchedRate($upazilaId, $districtId, $divisionId)
                    : null;
                $deliveryZoneSnapshot = $matchedRate?->name;
                // Try geo quote only when shipping address has geo info; otherwise trust client-provided/method cost (preserves tests with no address)
                $geoCost = null;
                if ($upazilaId !== null || $districtId !== null || $divisionId !== null) {
                    $geoCost = $shippingService->quote($upazilaId, $districtId, $divisionId, null, $subtotalAfterDiscountForShipping);
                }
                $fallbackCost = $methodForCheck?->cost ?? ($orderData['shipping_cost'] ?? 0);
                $shippingCost = $geoCost ?? $fallbackCost;
                $freeShippingReason = $shippingCost === 0 && $geoCost === 0 ? 'geo_threshold' : null;
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
                    'delivery_zone_snapshot' => $deliveryZoneSnapshot ?? null,
                    'discount_total' => $discountTotal,
                    'tax_total' => $orderData['tax_total'] ?? 0,
                    'shipping_address_id' => $orderData['shipping_address_id'] ?? null,
                    'shipping_address_snapshot' => $orderData['shipping_address'] ?? null,
                    'billing_address_id' => $orderData['billing_address_id'] ?? null,
                    'billing_address_snapshot' => $orderData['billing_address'] ?? null,
                    'customer_note' => $orderData['customer_note'] ?? null,
                    'preorder_ack_at' => $orderData['preorder_ack_at'] ?? null,
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

            // Partition fulfillments by fulfillment_strategy (stock vs preorder vs dropship).
            // Single strategy → one fulfillment (backward compat for all-stock tests).
            // Mixed → one per strategy, preorder fulfillment gets earliest ETA.
            $strategyGroups = [];
            foreach ($cart->items as $item) {
                $variant = $variants->get($item->product_variant_id);
                $strategy = $variant->fulfillment_strategy->value;
                $strategyGroups[$strategy][] = $item;
            }

            $fulfillmentByStrategy = [];
            foreach ($strategyGroups as $strategy => $groupItems) {
                $expectedAt = null;
                if ($strategy === FulfillmentStrategy::Preorder->value) {
                    $etas = collect($groupItems)
                        ->map(fn ($ci) => $variants->get($ci->product_variant_id)->expected_available_at)
                        ->filter()
                        ->sort();
                    $expectedAt = $etas->first();
                }

                $fulfillmentByStrategy[$strategy] = $order->fulfillments()->create([
                    'tenant_id' => $order->tenant_id,
                    'status' => OrderFulfillmentStatus::Pending,
                    'fulfillment_group' => $strategy,
                    'expected_available_at' => $expectedAt,
                ]);
            }

            foreach ($cart->items as $item) {
                $variant = $variants->get($item->product_variant_id);
                $strategy = $variant->fulfillment_strategy->value;
                $unitCostPrice = (int) ($variant->cost_price ?? 0);
                $unitWeight = $variant->weight_grams;

                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'order_fulfillment_id' => $fulfillmentByStrategy[$strategy]->id ?? null,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name_snapshot' => $variant->product->name ?? $variant->sku,
                    'variant_sku_snapshot' => $variant->sku,
                    'unit_price' => $this->pricing->resolveUnitPrice($variant),
                    'unit_cost_price' => $unitCostPrice,
                    'quantity' => (string) $item->quantity,
                    'line_total' => $this->pricing->calculateLineTotal($variant, (string) $item->quantity),
                    'line_cost' => $this->lineTotal($unitCostPrice, (string) $item->quantity),
                    'unit_weight_grams' => $unitWeight,
                    'line_weight_grams' => $unitWeight !== null ? (int) round((float) $unitWeight * (float) ((string) $item->quantity)) : null,
                    'fulfillment_strategy' => $strategy,
                    'expected_available_at' => $variant->expected_available_at,
                ]);

                $this->inventory->reserve($variant, (string) $item->quantity, null, $order);
            }

            // Authoritative totals single path — subtotal and grand_total are
            // derived here from the line items, never set independently.
            $this->recalculateTotals($order);

            $status = OrderStatus::Pending->label();

            $metadata = [];
            if (isset($freeShippingReason) && $freeShippingReason !== null) {
                $metadata['free_shipping_reason'] = $freeShippingReason;
            }
            if ($couponResult->valid && $couponResult->coupon) {
                $metadata['coupon_code'] = $couponResult->coupon->code ?? 'AUTOMATIC';
                $metadata['coupon_id'] = $couponResult->coupon->id;
            }

            $this->logEvent(
                $order,
                OrderEventType::StatusChanged,
                "Order placed as {$status}.",
                null,
                OrderStatus::Pending,
                $metadata !== [] ? $metadata : null,
            );

            $cart->update(['converted_at' => now()]);
            // Explicit coupon redemption (discount >0)
            $this->coupons->recordRedemption($order, $cart, $cart->customer, $discountTotal);
            // Automatic coupon redemption for Percentage/Fixed auto coupons (cart.coupon_id is null)
            if (! $cart->coupon_id && $couponResult->valid && $couponResult->coupon) {
                $this->coupons->recordRedemptionForResult($order, $couponResult, $cart->customer);
            }
            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * Admin-originated order — no Cart, direct variant selection.
     * Enterprise: deterministic variant/stock locks, full price & stock validation,
     * split fulfillments, and OrderPlaced dispatch, without cart conversion or
     * reservation-limit checks (admin bypass).
     *
     * @param  array<int, array{product_variant_id:int, quantity:int}>  $lines
     * @param  array{customer_id?:?int, guest_name?:?string, guest_email?:?string, guest_phone?:?string, shipping_address?: array, billing_address?: array, shipping_address_id?:?int, billing_address_id?:?int, shipping_method_id?:?int, payment_method_id?:?int, customer_note?:?string, shipping_cost?:int, tax_total?:int, preorder_ack_at?:CarbonInterface|string|null}  $orderData
     */
    public function createFromAdmin(array $lines, array $orderData, OrderSource $source = OrderSource::Admin): Order
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('At least one line item is required.');
        }

        $customerId = $orderData['customer_id'] ?? null;

        if (! $customerId && (empty($orderData['guest_name']) || empty($orderData['guest_email']) || empty($orderData['guest_phone']))) {
            throw new \InvalidArgumentException('Guest orders require guest_name, guest_email, and guest_phone.');
        }

        return DatabaseLockRetry::run(function () use ($lines, $orderData, $source, $customerId): Order {
            $tenantId = $orderData['tenant_id'] ?? tenant()?->id ?? throw new \RuntimeException('Tenant context required for admin order creation.');

            $variantIds = collect($lines)->pluck('product_variant_id')->unique()->sort()->values();
            $variants = ProductVariant::query()->whereIn('id', $variantIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($variants->count() !== $variantIds->count()) {
                throw new InvalidOrderStateException('A selected variant is no longer available in this store.');
            }

            $variants->load('product');
            $this->inventory->lockStockItemsForVariants($variants);

            foreach ($lines as $line) {
                $variant = $variants->get($line['product_variant_id']);
                $qty = number_format((float) $line['quantity'], 3, '.', '');

                if (bccomp($qty, '0', 3) !== 1) {
                    throw new \InvalidArgumentException('Quantity must be at least 0.001.');
                }

                if (! $this->inventory->isPurchasable($variant, $qty)) {
                    throw new InsufficientStockException("'{$variant->sku}' is no longer available in the requested quantity.");
                }
            }

            $shippingCost = $orderData['shipping_cost'] ?? 0;
            $discountTotal = 0;

            $order = Order::query()->create([
                'tenant_id' => $tenantId,
                'order_number' => $this->sequences->nextFormatted($tenantId, 'order_number', 'ORD'),
                'invoice_number' => $this->sequences->nextFormatted($tenantId, 'invoice_number', 'INV'),
                'customer_id' => $customerId,
                'guest_name' => $orderData['guest_name'] ?? null,
                'guest_email' => $orderData['guest_email'] ?? null,
                'guest_phone' => $orderData['guest_phone'] ?? null,
                'status' => OrderStatus::Pending,
                'order_source' => $source,
                'sales_channel' => 'admin',
                'payment_method_id' => $orderData['payment_method_id'] ?? null,
                'shipping_method_id' => $orderData['shipping_method_id'] ?? null,
                'currency_code' => $orderData['currency_code'] ?? 'BDT',
                'currency_rate' => 1.000000,
                'shipping_cost' => $shippingCost,
                'discount_total' => $discountTotal,
                'tax_total' => $orderData['tax_total'] ?? 0,
                'shipping_address_id' => $orderData['shipping_address_id'] ?? null,
                'shipping_address_snapshot' => $orderData['shipping_address'] ?? null,
                'billing_address_id' => $orderData['billing_address_id'] ?? null,
                'billing_address_snapshot' => $orderData['billing_address'] ?? null,
                'customer_note' => $orderData['customer_note'] ?? null,
                'preorder_ack_at' => $orderData['preorder_ack_at'] ?? null,
                'reservation_expires_at' => now()->addHours((int) config('orders.reservation_hours')),
                'active_reservation_key' => null,
                'placed_at' => now(),
            ]);

            $strategyGroups = [];
            foreach ($lines as $line) {
                $variant = $variants->get($line['product_variant_id']);
                $strategy = $variant->fulfillment_strategy->value;
                $strategyGroups[$strategy][] = $line;
            }

            $fulfillmentByStrategy = [];
            foreach ($strategyGroups as $strategy => $groupLines) {
                $expectedAt = null;

                if ($strategy === FulfillmentStrategy::Preorder->value) {
                    $etas = collect($groupLines)->map(fn ($l) => $variants->get($l['product_variant_id'])->expected_available_at)->filter()->sort();
                    $expectedAt = $etas->first();
                }

                $fulfillmentByStrategy[$strategy] = $order->fulfillments()->create([
                    'tenant_id' => $order->tenant_id,
                    'status' => OrderFulfillmentStatus::Pending,
                    'fulfillment_group' => $strategy,
                    'expected_available_at' => $expectedAt,
                ]);
            }

            foreach ($lines as $line) {
                $variant = $variants->get($line['product_variant_id']);
                $qty = number_format((float) $line['quantity'], 3, '.', '');
                $strategy = $variant->fulfillment_strategy->value;
                $unitCostPrice = (int) ($variant->cost_price ?? 0);
                $unitWeight = $variant->weight_grams;

                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'order_fulfillment_id' => $fulfillmentByStrategy[$strategy]->id ?? null,
                    'product_variant_id' => $variant->id,
                    'product_name_snapshot' => $variant->product->name ?? $variant->sku,
                    'variant_sku_snapshot' => $variant->sku,
                    'unit_price' => $this->pricing->resolveUnitPrice($variant),
                    'unit_cost_price' => $unitCostPrice,
                    'quantity' => $qty,
                    'line_total' => $this->pricing->calculateLineTotal($variant, $qty),
                    'line_cost' => $this->lineTotal($unitCostPrice, $qty),
                    'unit_weight_grams' => $unitWeight,
                    'line_weight_grams' => $unitWeight !== null ? (int) round((float) $unitWeight * (float) $qty) : null,
                    'fulfillment_strategy' => $strategy,
                    'expected_available_at' => $variant->expected_available_at,
                ]);

                $this->inventory->reserve($variant, $qty, null, $order);
            }

            $this->recalculateTotals($order);

            $this->logEvent($order, OrderEventType::StatusChanged, 'Order placed as '.OrderStatus::Pending->label().' (admin).', null, OrderStatus::Pending);

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    public function updateStatus(Order $order, OrderStatus $newStatus, ?string $note = null): Order
    {
        $fresh = null;

        DatabaseLockRetry::run(function () use ($order, $newStatus, $note, &$fresh): void {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStatusTransition($lockedOrder, $newStatus);
            $this->applyStatusTransition($lockedOrder, $newStatus, $note);

            $fresh = $lockedOrder->fresh();
        });

        return $fresh ?? $order->fresh();
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
            $variants = $order->items->pluck('variant')->filter();
            $this->inventory->lockStockItemsForVariants($variants);
            $this->inventory->lockProductsForVariants($variants);

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
            $variants = $order->items->pluck('variant')->filter();
            $this->inventory->lockStockItemsForVariants($variants);
            $this->inventory->lockProductsForVariants($variants);

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
                'Refund required: this cancelled order already has '.number_format($this->amountPaid($order) / 100, 2).' paid. Issue a refund from the order screen.'
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
        return DatabaseLockRetry::run(function () use ($order, $method, $amount, $status, $reference): OrderPayment {
            // The paid total is read and then written against, so the order row
            // must be locked for the whole check-then-act: without it two
            // concurrent recordings both read the same $paidAlready, both pass the
            // remaining-due guard, and together over-collect. The unique
            // (tenant_id, transaction_reference) index cannot cover this case —
            // cash has no gateway reference, and MySQL treats every NULL in a
            // unique index as distinct.
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($amount <= 0) {
                throw new InvalidOrderStateException('Payment amount must be greater than zero.');
            }

            if ($locked->status === OrderStatus::Cancelled) {
                throw new InvalidOrderStateException('Payments cannot be recorded on a cancelled order.');
            }

            $paidAlready = $this->amountPaid($locked);

            if ($status === OrderPaymentStatus::Paid) {
                $remainingDue = (int) $locked->grand_total - $paidAlready;

                if ($remainingDue <= 0) {
                    throw new InvalidOrderStateException('This order is already fully paid — no amount is due.');
                }

                if ($amount > $remainingDue) {
                    throw new InvalidOrderStateException(
                        'Payment of '.number_format($amount / 100, 2).' exceeds the remaining due of '.number_format($remainingDue / 100, 2).'.'
                    );
                }
            }

            $payment = $locked->payments()->create([
                'tenant_id' => $locked->tenant_id,
                'payment_method_id' => $method?->id,
                'amount' => $amount,
                'status' => $status,
                'transaction_reference' => $reference,
                'paid_at' => $status === OrderPaymentStatus::Paid ? now() : null,
            ]);

            $this->logEvent(
                $locked,
                OrderEventType::PaymentRecorded,
                'Payment of '.number_format($amount / 100, 2)." recorded as {$status->label()}.",
            );

            OrderPaymentRecorded::dispatch($payment);

            if ($status === OrderPaymentStatus::Paid
                && $locked->status === OrderStatus::Pending
                && config('orders.auto_confirm_on_full_payment')
                && ($paidAlready + $amount) >= (int) $locked->grand_total
            ) {
                $this->updateStatus($locked, OrderStatus::Confirmed, 'Confirmed — order fully paid.');
            }

            return $payment;
        });
    }

    /**
     * Books the cash a courier collected on delivery for one consignment.
     *
     * COD orders deliberately carry no payment row until the money actually
     * exists, so this is the event that creates it. The reference is derived from
     * the consignment rather than supplied, which turns the existing unique
     * (tenant_id, transaction_reference) index into the idempotency key: a second
     * attempt — a duplicate click, two app servers, a re-run of the poller —
     * cannot double-book the cash.
     *
     * Returns null when the collection was already recorded, rather than throwing.
     * A repeat is expected rather than exceptional, so it must be a silent no-op,
     * the same contract claimPendingOrder() uses for payment callbacks.
     *
     * One row per consignment is the deliberate limit of that idempotency: if a
     * courier later remits a shortfall, record the balance through recordPayment()
     * with its own reference. Reusing this method would look like a duplicate.
     */
    public function recordCodCollection(OrderFulfillment $fulfillment, int $amount, ?PaymentMethod $method = null): ?OrderPayment
    {
        $order = $fulfillment->order()->firstOrFail();
        $reference = 'cod:'.$fulfillment->getKey();

        if ($order->payments()->where('transaction_reference', $reference)->exists()) {
            return null;
        }

        try {
            return $this->recordPayment(
                $order,
                $method ?? $order->paymentMethod,
                $amount,
                OrderPaymentStatus::Paid,
                $reference,
            );
        } catch (UniqueConstraintViolationException) {
            // The check above lost a race. The unique index, not the check, is the
            // actual guarantee — this is just how the loser reports it.
            return null;
        }
    }

    public function amountRefunded(Order $order): int
    {
        return (int) $order->payments()->where('status', OrderPaymentStatus::Refunded)->sum('amount');
    }

    /**
     * Record a refund for an order — enterprise-grade partial/full support.
     * Creates an OrderPayment with status Refunded, validates against paid/refunded totals,
     * and auto-transitions to Refunded status when fully refunded from Cancelled/Delivered.
     *
     * @param  bool  $restock  return the order's goods to the sellable pool. Opt-in
     *                         because money coming back does not always mean goods
     *                         came back — see assertRestockable() for the rules.
     */
    public function refund(Order $order, int $amount, string $reason, ?PaymentMethod $method = null, ?string $reference = null, bool $restock = false): OrderPayment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for a refund.');
        }

        return DatabaseLockRetry::run(function () use ($order, $amount, $reason, $method, $reference, $restock): OrderPayment {
            // Same check-then-act as recordPayment: the refundable amount is read
            // and then written against, so without the row lock two concurrent
            // refunds can both pass the guard and together exceed what was paid.
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($amount <= 0) {
                throw new InvalidOrderStateException('Refund amount must be greater than zero.');
            }

            $paid = $this->amountPaid($locked);
            $refunded = $this->amountRefunded($locked);
            $refundable = $paid - $refunded;

            // This is the only state gate a refund needs: an order with nothing
            // left to refund cannot be refunded, whatever status it is in.
            if ($refundable <= 0) {
                throw new InvalidOrderStateException('No refundable amount remains for this order.');
            }

            if ($amount > $refundable) {
                throw new InvalidOrderStateException(
                    'Refund of '.number_format($amount / 100, 2).' exceeds refundable amount of '.number_format($refundable / 100, 2).'.'
                );
            }

            $isFullRefund = ($refunded + $amount) >= $paid;

            if ($restock) {
                $this->assertRestockable($locked, $isFullRefund);
            }

            $payment = $locked->payments()->create([
                'tenant_id' => $locked->tenant_id,
                'payment_method_id' => $method?->id ?? $locked->payment_method_id,
                'amount' => $amount,
                'status' => OrderPaymentStatus::Refunded,
                'transaction_reference' => $reference,
                'paid_at' => null,
            ]);

            $metadata = ['amount' => $amount, 'reason' => $reason, 'reference' => $reference];

            if ($restock) {
                // Proof in the audit trail that the goods were returned exactly
                // once, and which serials came back.
                $metadata['restocked'] = true;
                $metadata['returned_serials'] = $this->restockRefundedItems($locked);
            }

            $this->logEvent(
                $locked,
                OrderEventType::PaymentRecorded,
                'Refund of '.number_format($amount / 100, 2).' recorded: '.$reason,
                metadata: $metadata,
            );

            OrderPaymentRecorded::dispatch($payment);

            if ($isFullRefund && in_array($locked->status, [OrderStatus::Cancelled, OrderStatus::Delivered], true)) {
                $this->updateStatus($locked, OrderStatus::Refunded, 'Order fully refunded: '.$reason);
            }

            return $payment;
        });
    }

    /**
     * Returning goods to the sellable pool is only sound in one situation: the
     * order was delivered, and the whole of it has now come back.
     *
     * Cancelled is refused because applyStatusTransition() already restocked the
     * order when it was cancelled — restocking again would double-count stock.
     *
     * A partial refund is refused because a money amount cannot say *which* items
     * came back; inferring quantities from a decimal would corrupt the ledger.
     * Per-item returns need an RMA record, which this phase does not have.
     */
    private function assertRestockable(Order $order, bool $isFullRefund): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw new InvalidOrderStateException(
                'This order was already restocked when it was cancelled. Record the refund without returning goods to stock.'
            );
        }

        if ($order->status !== OrderStatus::Delivered) {
            throw new InvalidOrderStateException(
                'Goods can only be returned to stock on a delivered order — this order is '.$order->status->label().'.'
            );
        }

        if (! $isFullRefund) {
            throw new InvalidOrderStateException(
                'A partial refund cannot determine which items were returned. Refund in full to return goods to stock.'
            );
        }
    }

    /**
     * @return array<string, array<int, string>> variant SKU snapshot => returned serials
     */
    private function restockRefundedItems(Order $order): array
    {
        $returnedSerials = [];

        // Ascending variant + product order, the same as every other inventory path, so a
        // refund can never deadlock against a cancellation or a commit. Product rows
        // locked deterministically (asc product_id) before stock rows for decision 9/10.
        $variants = $order->items->pluck('variant')->filter();
        $this->inventory->lockProductsForVariants($variants);
        $this->inventory->lockStockItemsForVariants($variants);

        foreach ($order->items as $item) {
            if (! $item->variant) {
                continue;
            }

            $returned = $this->inventory->restockFromCancellation($item->variant, $item->quantity, null, $order, $item);

            if ($returned->isNotEmpty()) {
                $returnedSerials[$item->variant_sku_snapshot] = $returned->pluck('imei_or_serial')->all();
            }
        }

        return $returnedSerials;
    }

    /**
     * Records a consignment's state and lets it drive the order.
     *
     * Propagation lives here, not in CourierService, so that all three callers —
     * the admin action, CourierService::syncStatus() and the hourly poller — get
     * the same behaviour from one place.
     */
    public function updateFulfillment(OrderFulfillment $fulfillment, OrderFulfillmentStatus $status, ?string $trackingNumber = null, ?string $courierName = null): void
    {
        DatabaseLockRetry::run(function () use ($fulfillment, $status, $trackingNumber, $courierName): void {
            // Parent before child, matching the stock_items -> serial_numbers
            // convention: a single global lock order is what stops two
            // consignments on one order from deadlocking against each other.
            $order = Order::query()
                ->whereKey($fulfillment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = OrderFulfillment::query()
                ->whereKey($fulfillment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'status' => $status,
                'tracking_number' => $trackingNumber ?? $locked->tracking_number,
                'courier_name' => $courierName ?? $locked->courier_name,
                'shipped_at' => $status === OrderFulfillmentStatus::Shipped ? now() : $locked->shipped_at,
                'delivered_at' => $status === OrderFulfillmentStatus::Delivered ? now() : $locked->delivered_at,
            ]);

            $this->logEvent($order, OrderEventType::FulfillmentUpdated, "Fulfillment marked as {$status->label()}.");

            $this->propagateFulfillmentToOrder($order);

            // Keep the caller's instance in step with what was actually written.
            $fulfillment->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Lets the couriers' reports drive the order's own status.
     *
     * Deliberately an aggregate over every consignment rather than a reaction to
     * one: an order split by FulfillmentStrategy (stock / preorder / dropship)
     * legitimately has consignments that move at different times, so only the
     * state of all of them can say what the order is.
     *
     * Never throws. The hourly poller observes the same courier status over and
     * over, so anything already handled is a silent no-op — the same contract
     * claimPendingOrder() uses for payment callbacks.
     */
    private function propagateFulfillmentToOrder(Order $order): void
    {
        // A late courier report does not resurrect a terminal order.
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Refunded, OrderStatus::Cancelled], true)) {
            return;
        }

        $fulfillments = $order->fulfillments()->get(['id', 'status']);

        if ($fulfillments->isEmpty()) {
            return;
        }

        $statuses = $fulfillments->map(fn (OrderFulfillment $fulfillment): OrderFulfillmentStatus => $fulfillment->status);

        if ($statuses->every(fn (OrderFulfillmentStatus $status): bool => $status === OrderFulfillmentStatus::Delivered)) {
            if ($order->status !== OrderStatus::Shipped) {
                // Reaching Delivered from Confirmed/Processing would mean
                // inventing a Shipped event that never happened, putting false
                // history in the timeline. Leave the order where staff put it and
                // tell them what is blocking it instead.
                $this->logPropagationOnce(
                    $order,
                    OrderEventType::FulfillmentUpdated,
                    'delivered_awaiting_shipped',
                    'Every consignment is delivered, but this order is '.$order->status->label().'. Move it to Shipped to complete delivery.',
                );

                return;
            }

            $this->applyStatusTransition($order, OrderStatus::Delivered, 'Delivered — every consignment was delivered by the courier.');

            return;
        }

        $failed = $fulfillments->filter(fn (OrderFulfillment $fulfillment): bool => $fulfillment->status === OrderFulfillmentStatus::Failed);

        if ($failed->isEmpty()) {
            return;
        }

        if ($failed->count() === $fulfillments->count()) {
            // Log, never auto-cancel. Cancelling restocks, releases the coupon and
            // cannot be undone — Cancelled has no path back to Delivered — so a
            // courier that reports failed and later corrects itself would leave
            // the order permanently wrong. A human decides.
            $this->logPropagationOnce(
                $order,
                OrderEventType::FinancialAdjustmentRequired,
                'all_consignments_failed',
                'The courier returned every consignment. Cancel the order to restock and release the coupon, or reship — nothing was changed automatically.',
            );

            return;
        }

        $this->logPropagationOnce(
            $order,
            OrderEventType::FinancialAdjustmentRequired,
            'partial_failure:'.$failed->pluck('id')->sort()->implode(','),
            'The courier returned part of this order (consignment '.$failed->pluck('id')->sort()->implode(', ').'). The rest is still in transit — reship or refund the returned part.',
        );
    }

    /**
     * Writes a propagation notice at most once per distinct situation.
     *
     * The poller re-observes the same courier status every hour, so an
     * unguarded log would bury the order timeline in identical rows. The key is
     * matched against the event's own metadata, which makes the guard exact
     * rather than a string comparison on the description.
     */
    private function logPropagationOnce(Order $order, OrderEventType $type, string $key, string $description): void
    {
        $alreadyLogged = $order->events()
            ->where('type', $type)
            ->where('metadata->propagation', $key)
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        $this->logEvent($order, $type, $description, metadata: ['propagation' => $key]);
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
     * persist a state that is impossible or already overpaid — lowering a total
     * below what was collected would create an unrecorded liability, so the
     * refund has to be recorded first and the total lowered after.
     */
    public function recalculateTotals(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $subtotal = (int) $order->items()->sum('line_total');
            $costTotal = (int) $order->items()->sum('line_cost');
            $totalWeight = (int) round((float) $order->items()->sum('line_weight_grams'));
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
                    'Cannot lower the order total below the '.number_format($paid / 100, 2).' already paid. Refund the difference first, then lower the total.'
                );
            }

            $order->update(['subtotal' => $subtotal, 'cost_total' => $costTotal, 'total_weight_grams' => $totalWeight, 'grand_total' => $grandTotal]);
        }, 3);
    }

    /**
     * Decimal-safe line total (cents, integer) from quantity × unit_price.
     * Uses bcmath with half-up rounding so 0.333 × 333 = 111 not 110.
     */
    private function lineTotal(int $unitPrice, int|float|string $quantity): int
    {
        $qty = number_format((float) $quantity, 3, '.', '');
        $raw = bcmul((string) $unitPrice, $qty, 3);

        return (int) bcadd($raw, '0.5', 0);
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

    public function addItem(Order $order, ProductVariant $variant, int|float|string $quantity): OrderItem
    {
        $this->assertPendingEditable($order);

        $qty = number_format((float) $quantity, 3, '.', '');

        if (bccomp($qty, '0', 3) !== 1) {
            throw new \InvalidArgumentException('Quantity must be at least 0.001.');
        }

        return DB::transaction(function () use ($order, $variant, $qty): OrderItem {
            $this->inventory->reserve($variant, $qty, null, $order);

            $resolvedUnitPrice = $this->pricing->resolveUnitPrice($variant);
            $unitCostPrice = (int) ($variant->cost_price ?? 0);
            $unitWeight = $variant->weight_grams;
            $item = $order->items()->create([
                'tenant_id' => $order->tenant_id,
                'product_variant_id' => $variant->id,
                'product_name_snapshot' => $variant->product?->name ?? $variant->sku,
                'variant_sku_snapshot' => $variant->sku,
                'unit_price' => $resolvedUnitPrice,
                'unit_cost_price' => $unitCostPrice,
                'quantity' => $qty,
                'line_total' => $this->pricing->calculateLineTotal($variant, $qty),
                'line_cost' => $this->lineTotal($unitCostPrice, $qty),
                'unit_weight_grams' => $unitWeight,
                'line_weight_grams' => $unitWeight !== null ? (int) round((float) $unitWeight * (float) $qty) : null,
            ]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::ItemAdded,
                'Added '.$qty.' × '.$variant->sku.' at '.number_format($resolvedUnitPrice / 100, 2).' each.',
                metadata: ['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'quantity' => $qty, 'unit_price' => $resolvedUnitPrice],
            );

            return $item;
        }, 3);
    }

    public function updateItemQuantity(Order $order, OrderItem $item, int|float|string $quantity): void
    {
        $this->assertPendingEditable($order);
        $this->assertItemBelongsToOrder($order, $item);

        $qty = number_format((float) $quantity, 3, '.', '');

        if (bccomp($qty, '0', 3) !== 1) {
            throw new \InvalidArgumentException('Quantity must be at least 0.001.');
        }

        $beforeQuantity = (string) $item->quantity;

        if (bccomp($qty, $beforeQuantity, 3) === 0) {
            return;
        }

        $variant = $item->variant;

        if ($variant === null) {
            throw new InvalidOrderStateException('Item has no variant and cannot be edited.');
        }

        $delta = bcsub($qty, $beforeQuantity, 3);

        DB::transaction(function () use ($order, $item, $variant, $qty, $delta, $beforeQuantity): void {
            if (bccomp($delta, '0', 3) === 1) {
                $this->inventory->reserve($variant, $delta, null, $order);
            } else {
                $this->inventory->release($variant, bcsub('0', $delta, 3), null, $order);
            }

            $item->update([
                'quantity' => $qty,
                'line_total' => $this->lineTotal((int) $item->unit_price, $qty),
                'line_cost' => $this->lineTotal((int) ($item->unit_cost_price ?? 0), $qty),
                'line_weight_grams' => $item->unit_weight_grams !== null ? (int) round((float) $item->unit_weight_grams * (float) $qty) : null,
            ]);

            $this->recalculateTotals($order);

            $this->logEvent(
                $order,
                OrderEventType::ItemUpdated,
                "Quantity for {$variant->sku} updated from {$beforeQuantity} to {$qty}.",
                metadata: ['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'before_quantity' => $beforeQuantity, 'after_quantity' => $qty],
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

            $unitCostPrice = (int) ($newVariant->cost_price ?? 0);
            $unitWeight = $newVariant->weight_grams;

            $item->update([
                'product_variant_id' => $newVariant->id,
                'product_name_snapshot' => $newVariant->product?->name ?? $newVariant->sku,
                'variant_sku_snapshot' => $newVariant->sku,
                'unit_price' => $this->pricing->resolveUnitPrice($newVariant),
                'unit_cost_price' => $unitCostPrice,
                'line_total' => $this->pricing->calculateLineTotal($newVariant, (string) $quantity),
                'line_cost' => $this->lineTotal($unitCostPrice, (string) $quantity),
                'unit_weight_grams' => $unitWeight,
                'line_weight_grams' => $unitWeight !== null ? (int) round((float) $unitWeight * (float) ((string) $quantity)) : null,
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
                'line_total' => $this->lineTotal($unitPrice, (string) $item->quantity),
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
