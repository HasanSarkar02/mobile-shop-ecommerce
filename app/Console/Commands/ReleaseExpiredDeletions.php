<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantDeletionService;
use Illuminate\Console\Command;

class ReleaseExpiredDeletions extends Command
{
    protected $signature = 'tenants:release-expired-deletions';

    protected $description = 'Soft-delete tenants whose pending_deletion grace period has expired';

    public function handle(TenantDeletionService $deletions): int
    {
        $count = $deletions->releaseExpiredDeletions();

        $this->info("Soft-deleted {$count} expired pending_deletion tenants.");

        return self::SUCCESS;
    }
}
