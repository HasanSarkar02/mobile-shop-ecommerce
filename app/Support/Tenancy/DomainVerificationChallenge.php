<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Domain;
use Carbon\CarbonImmutable;

final readonly class DomainVerificationChallenge
{
    public function __construct(
        public Domain $domain,
        public string $recordName,
        public string $recordValue,
        public CarbonImmutable $expiresAt,
    ) {}
}
