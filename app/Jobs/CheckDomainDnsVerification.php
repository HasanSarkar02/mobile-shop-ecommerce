<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DomainDnsVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDomainDnsVerification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $domainId,
        public readonly string $expectedDigest,
    ) {
        $this->timeout = max(1, (int) config('domain-verification.job_timeout_seconds', 15));
    }

    public function uniqueId(): string
    {
        return $this->domainId.':'.$this->expectedDigest;
    }

    public function handle(DomainDnsVerificationService $verification): void
    {
        $result = $verification->check($this->domainId, $this->expectedDigest);

        if ($result->retryable) {
            $this->release(max(1, $result->retryAfterSeconds));
        }
    }
}
