<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Listeners\Concerns\BuildsOrderNotificationContext;
use App\Models\User;
use App\Notifications\NotificationRecipient;
use App\Services\NotificationService;

class SendOrderPlacedNotifications
{
    use BuildsOrderNotificationContext;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        $context = $this->buildOrderContext($order);

        $this->notifications->send('order.placed', $this->customerRecipient($order), $context);

        $owner = User::query()->where('tenant_id', $order->tenant_id)->where('role', 'owner')->first();

        if ($owner) {
            $this->notifications->send('order.placed.staff', new NotificationRecipient(
                audience: 'staff',
                modelType: User::class,
                modelId: $owner->id,
                addresses: ['email' => $owner->email],
            ), $context);
        }
    }
}