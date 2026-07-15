<?php

declare(strict_types=1);

return [
    'drivers' => [
        'email' => \App\Notifications\Channels\EmailChannelDriver::class,
        'sms' => \App\Notifications\Channels\SmsChannelDriver::class,
        // Future: 'whatsapp', 'push', 'webhook', 'in_app' — add a driver class,
        // register it here, no other code changes required.
    ],
];