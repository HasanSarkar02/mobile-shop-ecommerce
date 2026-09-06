<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantDeletionService;
use Illuminate\Console\Command;

class SuspendInactiveTenants extends Command
{
    protected $signature = 'tenants:suspend-inactive {--days=60 : Inactivity days before suspension}';

    protected $description = 'Suspend tenants inactive for N days (no login, no orders)';

    public function handle(TenantDeletionService $deletions): int
    {
        $days = (int) ($this->option('days') ?? config('tenancy.inactivity_suspend_days', 60));

        $count = $deletions->suspendInactive($days);

        $this->info("Suspended {$count} inactive tenants (>{$days}d).");

        return self::SUCCESS;
    }
}
