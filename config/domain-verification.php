<?php

declare(strict_types=1);

return [
    'driver' => env('DOMAIN_VERIFICATION_DRIVER', 'native'),
    'record_prefix' => env('DOMAIN_VERIFICATION_RECORD_PREFIX', '_mobile-shop-verification'),
    'challenge_ttl_hours' => (int) env('DOMAIN_VERIFICATION_TTL_HOURS', 72),
    'job_timeout_seconds' => (int) env('DOMAIN_VERIFICATION_JOB_TIMEOUT', 15),
    'max_attempts' => (int) env('DOMAIN_VERIFICATION_MAX_ATTEMPTS', 6),
    'manual_check_cooldown_seconds' => (int) env('DOMAIN_VERIFICATION_MANUAL_COOLDOWN_SECONDS', 30),
    'retry_backoff_seconds' => [30, 120, 300],
    'scheduler_cooldown_seconds' => (int) env('DOMAIN_VERIFICATION_SCHEDULER_COOLDOWN_SECONDS', 300),
];
