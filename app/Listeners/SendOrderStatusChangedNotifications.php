<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Listeners\Concerns\BuildsOrderNotificationContext;
use App\Services\NotificationService;

class SendOrderStatusChangedNotifications
{
    use BuildsOrderNotificationContext;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(OrderStatusChanged $event): void
    {
        $context = $this->buildOrderContext($event->order);
        $context['order']['from_status'] = $event->from->label();
        $context['order']['to_status'] = $event->to->label();

        $this->notifications->send('order.status_changed', $this->customerRecipient($event->order), $context);
    }
}