<?php

declare(strict_types=1);

return [
    'central_domain' => env('APP_DOMAIN', 'mobile-shop-ecommerce.test'),

    'reserved_subdomains' => [
    'www', 'admin', 'api', 'app', 'mail', 'ftp', 'smtp', 'ns1', 'ns2', 'store', 'shop',
    'dashboard', 'platform', 'central', 'staging', 'test', 'support', 'help',
    'blog', 'status', 'cdn', 'assets', 'static', 'docs', 'dev', 'billing', 'account', 'accounts',
    ],
    'trial_days' => env('TENANT_TRIAL_DAYS', 14),
];