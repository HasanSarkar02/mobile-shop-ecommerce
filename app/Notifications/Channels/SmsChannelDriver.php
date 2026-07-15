<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Support\Facades\Log;

/**
 * Placeholder driver — no real Bangladesh SMS gateway is integrated yet.
 * Logs the outgoing message so the full pipeline (template -> render -> queue -> log)
 * is testable end-to-end today. Swap for a real gateway by adding a driver class
 * and pointing config('notification_channels.drivers.sms') at it — no other
 * code changes required anywhere else in the system.
 */
class SmsChannelDriver implements NotificationChannelDriver
{
    public function send(string $address, ?string $subject, string $body): void
    {
        Log::info("[SMS stub] To: {$address} | Body: {$body}");
    }
}