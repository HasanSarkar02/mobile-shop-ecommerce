<?php

declare(strict_types=1);

use Illuminate\Http\Request;

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment mode
    |--------------------------------------------------------------------------
    |
    | SaaS resolves tenants from the request host. Dedicated deployments resolve
    | exactly one configured tenant and never consult arbitrary host mappings.
    |
    */
    'mode' => env('DEPLOYMENT_MODE', 'saas'),

    'dedicated' => [
        'tenant_id' => env('DEDICATED_TENANT_ID'),
        'canonical_host' => env('DEDICATED_CANONICAL_HOST'),
    ],

    /* Additional exact hosts or *.example.com patterns accepted by the host policy. */
    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ALLOWED_HOSTS', '')),
    ))),

    /* Only these proxies may provide forwarded host and scheme headers. */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

    'trusted_proxy_headers' => match (strtolower((string) env('TRUSTED_PROXY_HEADERS', 'all'))) {
        'host' => Request::HEADER_X_FORWARDED_HOST,
        'proto' => Request::HEADER_X_FORWARDED_PROTO,
        'port' => Request::HEADER_X_FORWARDED_PORT,
        'for' => Request::HEADER_X_FORWARDED_FOR,
        default => Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
            | Request::HEADER_X_FORWARDED_AWS_ELB,
    },

    'force_https' => filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL),

    'url_scheme' => env(
        'APP_SCHEME',
        parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_SCHEME) ?: 'http',
    ),
];
