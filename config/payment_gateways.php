<?php

declare(strict_types=1);
use App\Services\PaymentGateways\SslcommerzDriver;

return [
    'drivers' => [
        'sslcommerz' => SslcommerzDriver::class,
        // Future gateways (bKash direct API, Stripe, etc.) register here — one line, no other code changes.
    ],
];
