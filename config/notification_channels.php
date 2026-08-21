<?php

declare(strict_types=1);
use App\Notifications\Channels\EmailChannelDriver;
use App\Notifications\Channels\SmsChannelDriver;

return [
    'drivers' => [
        'email' => EmailChannelDriver::class,
        'sms' => SmsChannelDriver::class,
        // Future: 'whatsapp', 'push', 'webhook', 'in_app' — add a driver class,
        // register it here, no other code changes required.
    ],
];
