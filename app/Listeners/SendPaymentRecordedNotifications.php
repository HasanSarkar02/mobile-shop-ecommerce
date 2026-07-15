<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaymentRecorded;
use App\Listeners\Concerns\BuildsOrderNotificationContext;
use App\Services\NotificationService;

class SendPaymentRecordedNotifications
{
    use BuildsOrderNotificationContext;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(OrderPaymentRecorded $event): void
    {
        $order = $event->payment->order;
        $context = $this->buildOrderContext($order);
        $context['order']['payment_amount'] = number_format($event->payment->amount / 100, 2);

        $this->notifications->send('payment.recorded', $this->customerRecipient($order), $context);
    }
}