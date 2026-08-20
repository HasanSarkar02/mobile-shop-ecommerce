<?php

declare(strict_types=1);

namespace App\Support\Dns;

final readonly class DomainDnsVerificationResult
{
    public function __construct(
        public bool $verified,
        public bool $retryable,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public int $retryAfterSeconds = 0,
    ) {}
}
