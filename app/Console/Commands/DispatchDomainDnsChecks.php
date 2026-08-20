<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Services\DomainDnsVerificationService;
use Illuminate\Console\Command;

class DispatchDomainDnsChecks extends Command
{
    protected $signature = 'domains:dispatch-dns-checks';

    protected $description = 'Dispatches due pending domain DNS verification checks.';

    public function handle(DomainDnsVerificationService $verification): int
    {
        $cooldown = max(1, (int) config('domain-verification.scheduler_cooldown_seconds', 300));
        $maxAttempts = max(1, (int) config('domain-verification.max_attempts', 6));
        $cutoff = now()->subSeconds($cooldown);
        $dispatched = 0;

        Domain::query()
            ->where('status', DomainStatus::Pending)
            ->whereNotNull('verification_token_digest')
            ->whereNotNull('verification_expires_at')
            ->where('verification_expires_at', '>', now())
            ->where('verification_attempts', '<', $maxAttempts)
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $cutoff);
            })
            ->chunkById(100, function ($domains) use ($verification, &$dispatched): void {
                foreach ($domains as $domain) {
                    if ($verification->dispatchCheck($domain)) {
                        $dispatched++;
                    }
                }
            });

        $this->info("Dispatched {$dispatched} DNS verification check(s).");

        return self::SUCCESS;
    }
}
