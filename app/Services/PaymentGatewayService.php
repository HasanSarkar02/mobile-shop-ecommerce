<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    public function __construct(private readonly OrderService $orders)
    {
    }

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

    public function handleCallback(string $valId, string $tranId): void
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

        if (! $order || ! $order->paymentMethod?->gateway_driver) {
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
        }

        if ($status === OrderPaymentStatus::Paid && $order->status === OrderStatus::Pending) {
            $this->orders->updateStatus($order, OrderStatus::Confirmed, 'Payment confirmed via gateway.');
        }
    }
}