<?php

declare(strict_types=1);

return [
    'drivers' => [
        'sslcommerz' => \App\Services\PaymentGateways\SslcommerzDriver::class,
        // Future gateways (bKash direct API, Stripe, etc.) register here — one line, no other code changes.
    ],
];