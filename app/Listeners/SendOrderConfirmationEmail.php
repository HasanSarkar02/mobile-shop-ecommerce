<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        $order->loadMissing(['customer']);

        $recipient = $order->getAttribute('customer_id') !== null
            ? $order->getRelationValue('customer')?->getAttribute('email')
            : $order->getAttribute('guest_email');

        $recipient = is_string($recipient) ? trim($recipient) : null;

        if ($recipient === null || $recipient === '') {
            return;
        }

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Mail::to($recipient)->queue(new OrderConfirmationMail($order));
    }
}
