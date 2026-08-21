<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

interface NotificationChannelDriver
{
    /**
     * @throws \Throwable on delivery failure — caller's queue retry/backoff handles it.
     */
    public function send(string $address, ?string $subject, string $body): void;
}
