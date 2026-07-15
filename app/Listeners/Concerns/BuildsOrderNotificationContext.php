<?php

declare(strict_types=1);

namespace App\Listeners\Concerns;

use App\Models\Customer;
use App\Models\Order;
use App\Notifications\NotificationRecipient;

trait BuildsOrderNotificationContext
{
    protected function buildOrderContext(Order $order): array
    {
        return [
            'order' => [
                'number' => $order->order_number,
                'total' => number_format($order->grand_total / 100, 2),
                'status' => $order->status->label(),
            ],
            'customer' => [
                'name' => $order->customerDisplayName(),
                'email' => $order->customer?->email ?? $order->guest_email,
            ],
            'store' => ['name' => tenant()->name],
            'tracking' => ['url' => ''],
            'related_type' => Order::class,
            'related_id' => $order->id,
        ];
    }

    protected function customerRecipient(Order $order): NotificationRecipient
    {
        return new NotificationRecipient(
            audience: 'customer',
            modelType: $order->customer ? Customer::class : null,
            modelId: $order->customer?->id,
            addresses: array_filter([
                'email' => $order->customer?->email ?? $order->guest_email,
                'sms' => $order->customer?->phone ?? $order->guest_phone,
            ]),
        );
    }
}