<?php

declare(strict_types=1);
use App\Services\Shipping\PathaoDriver;
use App\Services\Shipping\SteadfastDriver;

return [
    'drivers' => [
        'steadfast' => SteadfastDriver::class,
        'pathao' => PathaoDriver::class,
        // Add future couriers here — one line, platform registers base_url via courier_providers table.
    ],
];
