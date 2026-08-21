<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantApprovalService;
use Illuminate\Console\Command;

class ReleaseExpiredPendingApprovals extends Command
{
    protected $signature = 'tenants:release-expired-pending-approvals';

    protected $description = 'Rejects pending tenant signups whose approval window has expired and frees their subdomains.';

    public function handle(TenantApprovalService $approvals): int
    {
        $count = $approvals->releaseExpiredPending((int) config('tenancy.pending_approval_expiry_days'));

        $this->info("Released {$count} expired pending approval(s).");

        return self::SUCCESS;
    }
}
