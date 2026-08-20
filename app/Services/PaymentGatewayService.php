<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    public function __construct(private readonly OrderService $orders) {}

    public function initiatePayment(Order $order): string
    {
        $method = $order->paymentMethod;

        abort_unless($method?->gateway_driver, 404, 'This order does not use an online payment gateway.');

        $driverClass = config("payment_gateways.drivers.{$method->gateway_driver}");
        abort_unless($driverClass, 404, 'Payment gateway not configured.');

        $tranId = "{$order->tenant_id}-{$order->order_number}";

        return app($driverClass)->initiate(
            $order,
            $tranId,
            route('storefront.payment.success'),
            route('storefront.payment.fail'),
            route('storefront.payment.cancel'),
            route('storefront.payment.ipn'),
        );
    }

    private function resolveOrderForTransaction(string $tranId): ?Order
    {
        $tenantId = tenant()?->id;

        if ($tenantId && Str::startsWith($tranId, "{$tenantId}-")) {
            $orderNumber = Str::after($tranId, "{$tenantId}-");
            $order = Order::query()->where('order_number', $orderNumber)->first();
        } else {
            $order = Order::query()
                ->get()
                ->first(fn (Order $o) => "{$o->tenant_id}-{$o->order_number}" === $tranId);
        }

        return $order?->paymentMethod?->gateway_driver ? $order : null;
    }

    public function handleCallback(string $valId, string $tranId): void
    {
        $order = $this->resolveOrderForTransaction($tranId);

        if (! $order) {
            return;
        }

        if (OrderPayment::query()->where('tenant_id', $order->tenant_id)->where('transaction_reference', $tranId)->exists()) {
            return;
        }

        $driverClass = config("payment_gateways.drivers.{$order->paymentMethod->gateway_driver}");
        $result = app($driverClass)->validateTransaction($valId);

        $statusProp = is_object($result) ? ($result->status ?? null) : ($result['status'] ?? null);
        $amountProp = is_object($result) ? ($result->amount ?? null) : ($result['amount'] ?? null);

        $isValidStatus = strtolower((string) $statusProp) === 'valid';
        $isMatchingAmount = (float) $amountProp == (float) $order->grand_total;

        $status = $isValidStatus && $isMatchingAmount
            ? OrderPaymentStatus::Paid
            : OrderPaymentStatus::Failed;

        try {
            $this->orders->recordPayment($order, $order->paymentMethod, $amountProp ?? $order->grand_total, $status, $tranId);
        } catch (UniqueConstraintViolationException) {
            return;
        } catch (InvalidOrderStateException) {
            // The order is already fully paid / overpaid relative to its due
            // amount, so the callback is satisfied — ignore instead of failing
            // the webhook with a 500.
            return;
        }

        if ($status === OrderPaymentStatus::Paid && $order->status === OrderStatus::Pending) {
            $this->orders->updateStatus($order, OrderStatus::Confirmed, 'Payment confirmed via gateway.');
        }

        if ($status === OrderPaymentStatus::Failed) {
            $this->orders->cancelPendingOrderReservation($order, 'Order cancelled — payment failed.');
        }
    }

    /**
     * Records a failed payment for a transaction and atomically releases the
     * Pending order's reservation. Idempotent: repeated fail/cancel callbacks
     * are harmless, and non-Pending orders are never touched.
     */
    public function markFailed(string $tranId, string $note): void
    {
        $order = $this->resolveOrderForTransaction($tranId);

        if (! $order) {
            return;
        }

        if (OrderPayment::query()->where('tenant_id', $order->tenant_id)->where('transaction_reference', $tranId)->exists()) {
            return;
        }

        try {
            $this->orders->recordPayment($order, $order->paymentMethod, (int) $order->grand_total, OrderPaymentStatus::Failed, $tranId);
        } catch (UniqueConstraintViolationException) {
            return;
        } catch (InvalidOrderStateException) {
            // The order is already cancelled — nothing further to release.
            return;
        }

        $this->orders->cancelPendingOrderReservation($order, $note);
    }
}
