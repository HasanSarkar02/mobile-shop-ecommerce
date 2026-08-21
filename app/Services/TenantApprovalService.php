<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ShopApprovedNotification;
use App\Notifications\ShopRejectedNotification;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Moderation gateway for the public signup approval gate. Approving a pending
 * tenant starts a fresh trial subscription; rejecting frees the reserved
 * subdomain while keeping the tenant row for the audit trail.
 */
class TenantApprovalService
{
    public const REJECTED_SUBDOMAIN_PREFIX = 'rejected-';

    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function approve(Tenant $tenant, ?User $actor = null): void
    {
        DB::transaction(function () use ($tenant, $actor): void {
            $this->assertPending($tenant);

            $trialPlan = Plan::query()->where('slug', 'trial')->where('is_active', true)->first();

            if ($trialPlan === null) {
                throw new DomainException('The trial plan is not available.');
            }

            $this->subscriptions->startTrial($tenant, $trialPlan, (int) config('tenancy.trial_days'));

            $this->log($tenant, $actor, 'tenant.approved', [
                'reviewed_by_user_id' => $actor?->id,
            ]);
        });

        $this->owner($tenant)?->notify(new ShopApprovedNotification($tenant));
    }

    public function reject(Tenant $tenant, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($tenant, $actor, $reason): void {
            $this->assertPending($tenant);

            $tenant->forceFill([
                'status' => 'rejected',
                'subdomain' => self::REJECTED_SUBDOMAIN_PREFIX.$tenant->getKey(),
            ])->save();

            $this->log($tenant, $actor, 'tenant.rejected', [
                'reviewed_by_user_id' => $actor?->id,
                'reason' => $reason,
            ]);
        });

        $this->owner($tenant)?->notify(new ShopRejectedNotification($tenant, $reason));
    }

    /**
     * Rejects pending signups whose approval window has elapsed and frees
     * their subdomains.
     */
    public function releaseExpiredPending(int $ttlDays): int
    {
        $cutoff = now()->subDays($ttlDays);
        $expired = Tenant::query()
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($expired as $tenant) {
            $this->reject($tenant, null, 'No approval received within the reservation window.');
        }

        return $expired->count();
    }

    private function assertPending(Tenant $tenant): void
    {
        if ($tenant->getAttribute('status') !== 'pending') {
            throw new DomainException('Only pending tenants can be approved or rejected.');
        }
    }

    private function owner(Tenant $tenant): ?User
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Tenant $tenant, ?User $actor, string $event, array $properties = []): void
    {
        $activity = activity('tenants')
            ->performedOn($tenant)
            ->event($event)
            ->withProperties($properties);

        if ($actor !== null) {
            $activity->causedBy($actor);
        } else {
            $activity->causedByAnonymous();
        }

        $activity->log($event);
    }
}
