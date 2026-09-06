<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TombstoneReason;
use App\Models\SubdomainTombstone;
use App\Models\Tenant;
use App\Services\TenantDeletionService;
use Illuminate\Console\Command;

class PurgeDeletedTenants extends Command
{
    protected $signature = 'tenants:purge-deleted';

    protected $description = 'Purge soft-deleted tenants whose quarantine has expired (tombstone quarantine_until passed). Tombstone survives purge.';

    public function handle(): int
    {
        $count = 0;

        // Expired quarantine: tombstone's quarantine passed, tenant already purged -> release tombstone (unless permanent)
        foreach (SubdomainTombstone::whereNotNull('quarantine_until')->where('quarantine_until', '<=', now())->get() as $tombstone) {
            $tenant = $tombstone->tenant_id ? Tenant::withTrashed()->find($tombstone->tenant_id) : null;
            if ($tenant && $tenant->trashed()) {
                app(TenantDeletionService::class)->purge($tenant, null, $tombstone->reason);
                $count++;
            } elseif (! $tenant && ! $tombstone->isPermanent()) {
                $tombstone->delete();
                $count++;
                $this->info("Released tombstone {$tombstone->subdomain}");
            }
        }

        // Soft-deleted tenants whose tombstone is 0-day (null quarantine) or no tombstone: purge immediately (not permanent)
        foreach (Tenant::onlyTrashed()->get() as $tenant) {
            $tomb = SubdomainTombstone::where('tenant_id', $tenant->id)->first();
            if ($tomb && $tomb->isPermanent()) {
                continue;
            }
            if ($tomb && $tomb->quarantine_until && $tomb->quarantine_until->isFuture()) {
                continue;
            }
            if ($tomb && $tomb->reason === TombstoneReason::Active30d && $tomb->quarantine_until === null) {
                // Should not happen: Active should have quarantine
                continue;
            }
            // Already handled via expired loop? Check if this tenant's tombstone was already processed
            $alreadyHandled = $tomb && $tomb->quarantine_until && $tomb->quarantine_until->isPast();
            if ($alreadyHandled) {
                continue;
            }
            if (! $tomb || $tomb->quarantine_until === null || $tomb->quarantine_until->isPast()) {
                // Avoid double count if tombstone was 0-day and tenant just soft-deleted: purge now
                if ($tomb && $tomb->reason === TombstoneReason::TrialZeroOrders) {
                    app(TenantDeletionService::class)->purge($tenant);
                    $count++;
                } elseif (! $tomb) {
                    // No tombstone (legacy rejected) -> purge directly
                    app(TenantDeletionService::class)->purge($tenant);
                    $count++;
                }
            }
        }

        $this->info("Purged {$count} tenants/tombstones.");

        return self::SUCCESS;
    }
}
