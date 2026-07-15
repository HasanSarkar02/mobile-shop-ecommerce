<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Listeners\Concerns\BuildsOrderNotificationContext;
use App\Services\NotificationService;

class SendOrderCancelledNotifications
{
    use BuildsOrderNotificationContext;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(OrderCancelled $event): void
    {
        $context = $this->buildOrderContext($event->order);

        $this->notifications->send('order.cancelled', $this->customerRecipient($event->order), $context);
    }
}