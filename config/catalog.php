<?php

declare(strict_types=1);

return [
    'new_arrival_days' => env('CATALOG_NEW_ARRIVAL_DAYS', 14),
    'cart_token_days' => env('CART_TOKEN_DAYS', 30),
    'wishlist_token_days' => env('WISHLIST_TOKEN_DAYS', 60),
    'recently_viewed_limit' => 20,
    'compare_limit' => 4,
];