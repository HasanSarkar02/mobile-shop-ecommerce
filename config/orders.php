<?php

declare(strict_types=1);

return [
    // Hours a Pending order holds its stock reservation before automatic release.
    'reservation_hours' => env('ORDER_RESERVATION_HOURS', 48),
];