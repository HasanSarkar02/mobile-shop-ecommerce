<?php

declare(strict_types=1);

return [
    // Hours a Pending order holds its stock reservation before automatic release.
    'reservation_hours' => env('ORDER_RESERVATION_HOURS', 48),

    // Maximum number of active Pending reservation orders a single identity
    // (customer_id or guest_email) may hold per tenant. The database enforces
    // the same bound via the unique (tenant_id, active_reservation_key) index.
    'max_pending_orders_per_identity' => env('ORDER_MAX_PENDING_ORDERS_PER_IDENTITY', 1),

    // Whether recording the final payment on a Pending order should automatically
    // confirm it. Defaults to false — staff confirmation remains the default.
    'auto_confirm_on_full_payment' => env('ORDER_AUTO_CONFIRM_ON_FULL_PAYMENT', false),
];
