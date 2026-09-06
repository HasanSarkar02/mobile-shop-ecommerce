<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\TombstoneReason;
use App\Jobs\TenantPurgeJob;
use App\Models\SubdomainTombstone;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDeletionService
{
    public const DELETED_PREFIX = 'deleted-';

    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Owner soft deletion only: schedule deletion with 7-day grace.
     * Platform can also call this to initiate soft delete.
     */
    public function requestDeletion(Tenant $tenant, ?User $actor = null, ?string $reason = null, TombstoneReason $tombstoneReason = TombstoneReason::Active30d): void
    {
        if ($tenant->trashed()) {
            throw new DomainException('Tenant is already deleted.');
        }

        if ($tenant->isPendingDeletion()) {
            throw new DomainException('Tenant deletion is already scheduled.');
        }

        if (! in_array($tenant->status, ['trial', 'active', 'suspended', 'pending'], true)) {
            throw new DomainException('Tenant cannot be scheduled for deletion in its current status.');
        }

        $graceDays = (int) config('tenancy.deletion_grace_days', 7);

        DB::transaction(function () use ($tenant, $actor, $reason, $tombstoneReason, $graceDays): void {
            $tenant->forceFill([
                'status' => 'pending_deletion',
                'deletion_requested_at' => now(),
                'deletion_scheduled_at' => now()->addDays($graceDays),
                'deletion_reason' => $reason,
                'deleted_by_id' => $actor?->id,
                'subdomain_original' => $tenant->subdomain,
            ])->save();

            $this->log($tenant, $actor, 'tenant.deletion_requested', [
                'reason' => $reason,
                'scheduled_at' => $tenant->deletion_scheduled_at?->toIso8601String(),
                'tombstone_reason' => $tombstoneReason->value,
            ]);

            // Stop renewal: cancel at period end by default (billing: stop renewal)
            try {
                $subscription = $tenant->subscription;
                if ($subscription && ! $subscription->trashed()) {
                    // Use SubscriptionService cancellation at period end if available, else direct
                    if (method_exists($this->subscriptions, 'cancelAtPeriodEnd')) {
                        $this->subscriptions->cancelAtPeriodEnd($tenant);
                    } else {
                        // Fallback: mark cancelled, keep entitlements until period end
                        $this->subscriptions->cancel($tenant);
                    }
                }
            } catch (\Throwable $e) {
                // Billing failure should not block deletion scheduling; log and continue
                $this->log($tenant, $actor, 'tenant.deletion_billing_cancel_failed', ['error' => $e->getMessage()]);
            }
        });
    }

    public function cancelDeletion(Tenant $tenant, ?User $actor = null): void
    {
        if (! $tenant->isPendingDeletion()) {
            throw new DomainException('Tenant is not pending deletion.');
        }

        DB::transaction(function () use ($tenant, $actor): void {
            // Restore status to prior active state if subscription still eligible, else suspended
            $restoreStatus = 'active';
            try {
                $subscription = $tenant->fresh()->subscription;
                if ($subscription) {
                    $restoreStatus = $this->subscriptions->hasEligibleSubscription($tenant) ? 'active' : 'suspended';
                } else {
                    $restoreStatus = 'trial';
                }
            } catch (\Throwable $e) {
                $restoreStatus = 'active';
            }

            $tenant->forceFill([
                'status' => $restoreStatus,
                'deletion_requested_at' => null,
                'deletion_scheduled_at' => null,
                'deletion_reason' => null,
                'deleted_by_id' => null,
            ])->save();

            $this->log($tenant, $actor, 'tenant.deletion_cancelled', []);
        });
    }

    /**
     * Soft delete: mutate subdomain immediately, create tombstone, revoke domains, set deleted_at.
     * Called by scheduled job after grace, or by platform force.
     */
    public function softDelete(Tenant $tenant, ?User $actor = null, ?TombstoneReason $reason = null, bool $force = false): void
    {
        if ($tenant->trashed()) {
            throw new DomainException('Tenant is already deleted.');
        }

        $wasPendingDeletion = $tenant->isPendingDeletion();
        $isTrialZeroOrders = $this->isTrialZeroOrders($tenant);
        $resolvedReason = $reason ?? ($isTrialZeroOrders ? TombstoneReason::TrialZeroOrders : TombstoneReason::Active30d);

        // Abuse permanent tombstone requires explicit reason
        if ($resolvedReason === TombstoneReason::Abuse && ! $force) {
            throw new DomainException('Permanent tombstone requires force confirmation.');
        }

        DB::transaction(function () use ($tenant, $actor, $resolvedReason, $wasPendingDeletion): void {
            $originalSubdomain = $tenant->subdomain_original ?? $tenant->subdomain;
            $tenant->forceFill([
                'subdomain_original' => $originalSubdomain,
                'subdomain' => self::DELETED_PREFIX.$tenant->getKey().'-'.Str::random(4),
                'status' => 'deleted',
            ])->save();

            $quarantineUntil = $resolvedReason->quarantineDays() !== null ? now()->addDays($resolvedReason->quarantineDays()) : null;

            SubdomainTombstone::create([
                'subdomain' => strtolower($originalSubdomain),
                'tenant_id' => $tenant->getKey(),
                'reason' => $resolvedReason,
                'released_at' => now(),
                'quarantine_until' => $quarantineUntil,
                'notes' => $wasPendingDeletion ? ($tenant->deletion_reason ?? '') : null,
            ]);

            // Revoke custom domains
            foreach ($tenant->domains as $domain) {
                if (in_array($domain->status->value, ['pending', 'verified', 'active', 'failed', 'suspended'], true)) {
                    $domain->forceFill([
                        'status' => DomainStatus::Revoked,
                        'revoked_at' => now(),
                        'revocation_reason' => 'Tenant deleted: '.($resolvedReason->value),
                    ])->save();
                }
            }
            if ($tenant->primary_domain_id) {
                $tenant->forceFill(['primary_domain_id' => null])->save();
            }

            $tenant->delete(); // soft delete

            $this->log($tenant, $actor, 'tenant.soft_deleted', [
                'original_subdomain' => $originalSubdomain,
                'reason' => $resolvedReason->value,
                'quarantine_until' => $quarantineUntil?->toIso8601String(),
            ]);
        });
    }

    /**
     * Force purge: platform admin only, async via TenantPurgeJob.
     * For abuse, creates permanent tombstone if not exists.
     */
    public function purge(Tenant $tenant, ?User $actor = null, ?TombstoneReason $reason = null): void
    {
        // Allow purging soft-deleted or with trashed
        $tenant = $tenant->trashed() ? $tenant : Tenant::withTrashed()->findOrFail($tenant->getKey());

        if (! $tenant->trashed()) {
            // If not yet soft-deleted, soft delete first
            $this->softDelete($tenant, $actor, $reason ?? TombstoneReason::Active30d, true);
            $tenant = Tenant::withTrashed()->findOrFail($tenant->getKey());
        }

        // Permanent tombstone for abuse should survive purge
        $subdomain = $tenant->subdomain_original ?? $tenant->subdomain;
        $existingTombstone = SubdomainTombstone::where('subdomain', strtolower($subdomain))->first();
        if ($reason === TombstoneReason::Abuse && ! $existingTombstone) {
            SubdomainTombstone::create([
                'subdomain' => strtolower($subdomain),
                'tenant_id' => $tenant->getKey(),
                'reason' => TombstoneReason::Abuse,
                'released_at' => now(),
                'quarantine_until' => null,
                'notes' => 'Force purge permanent',
            ]);
        }

        TenantPurgeJob::dispatch($tenant->getKey(), $actor?->id);
        $this->log($tenant, $actor, 'tenant.purge_queued', ['reason' => $reason?->value]);
    }

    public function releaseExpiredDeletions(): int
    {
        $expired = Tenant::where('status', 'pending_deletion')
            ->where('deletion_scheduled_at', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        foreach ($expired as $tenant) {
            $this->softDelete($tenant, null, null, false);
        }

        return $expired->count();
    }

    public function suspendInactive(int $days = 60): int
    {
        $cutoff = now()->subDays($days);
        // Tenant is inactive if no user has logged in recently and status is trial|active and not pending_deletion
        $candidates = Tenant::whereIn('status', ['trial', 'active'])
            ->where('deletion_scheduled_at', null)
            ->whereNull('deleted_at')
            ->get()
            ->filter(function (Tenant $tenant) use ($cutoff): bool {
                $lastActive = $tenant->users()->max('last_login_at') ?? $tenant->updated_at;
                // Fallback to updated_at if last_login_at not tracked; also check orders for activity
                if ($lastActive && $lastActive > $cutoff) {
                    return false;
                }
                // Check orders as secondary activity signal
                $recentOrder = DB::table('orders')
                    ->where('tenant_id', $tenant->id)
                    ->where('created_at', '>', $cutoff)
                    ->exists();
                if ($recentOrder) {
                    return false;
                }

                return true;
            });

        foreach ($candidates as $tenant) {
            $tenant->forceFill(['status' => 'suspended'])->save();
            $this->log($tenant, null, 'tenant.suspended_inactivity', ['cutoff' => $cutoff->toDateString()]);
        }

        return $candidates->count();
    }

    private function isTrialZeroOrders(Tenant $tenant): bool
    {
        if ($tenant->plan !== 'trial') {
            return false;
        }

        return ! DB::table('orders')->where('tenant_id', $tenant->id)->exists();
    }

    private function log(Tenant $tenant, ?User $actor, string $event, array $properties = []): void
    {
        $activity = activity('tenants')->performedOn($tenant)->event($event)->withProperties($properties);
        if ($actor) {
            $activity->causedBy($actor);
        } else {
            $activity->causedByAnonymous();
        }
        $activity->log($event);
    }
}
