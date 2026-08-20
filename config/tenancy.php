<?php

declare(strict_types=1);

return [
    'central_domain' => env('APP_DOMAIN', 'mobile-shop-ecommerce.test'),

    'reserved_subdomains' => [
        'www', 'admin', 'api', 'app', 'mail', 'ftp', 'smtp', 'ns1', 'ns2', 'store', 'shop',
        'dashboard', 'platform', 'central', 'staging', 'test', 'support', 'login', 'auth',
        'help', 'blog', 'status', 'cdn', 'assets', 'static', 'docs', 'dev', 'billing', 'account', 'accounts',
    ],
    'trial_days' => env('TENANT_TRIAL_DAYS', 14),
    'domain_verification_ttl_hours' => env('DOMAIN_VERIFICATION_TTL_HOURS', 72),
    'domain_verification_record_prefix' => env('DOMAIN_VERIFICATION_RECORD_PREFIX', '_mobile-shop-verification'),
];
