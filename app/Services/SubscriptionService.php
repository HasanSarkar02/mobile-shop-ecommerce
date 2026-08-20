<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeploymentMode;
use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Product;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\PlanChangeApprovedNotification;
use App\Notifications\PlanChangeRejectedNotification;
use App\Notifications\SubscriptionExpiredNotification;
use App\Support\Tenancy\Tenancy;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Single gateway for subscription lifecycle and plan-based quota enforcement.
 */
class SubscriptionService
{
    public function startTrial(Tenant $tenant, Plan $trialPlan, int $trialDays): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $trialPlan, $trialDays): TenantSubscription {
            return $this->applySubscriptionChange(
                $tenant,
                SubscriptionEventType::TrialStarted,
                null,
                $trialPlan,
                [
                    'plan_id' => $trialPlan->id,
                    'status' => SubscriptionStatus::Trialing,
                    'current_period_starts_at' => now(),
                    'current_period_ends_at' => now()->addDays($trialDays),
                    'cancelled_at' => null,
                ],
                metadata: ['trial_days' => $trialDays],
            );
        });
    }

    public function activatePlan(Tenant $tenant, Plan $plan): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $plan): TenantSubscription {
            if (! $plan->is_active) {
                throw new DomainException('The requested plan is not available.');
            }

            return $this->applySubscriptionChange(
                $tenant,
                SubscriptionEventType::Subscribed,
                null,
                $plan,
                [
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'current_period_starts_at' => now(),
                    'current_period_ends_at' => $this->periodEnd($plan),
                    'cancelled_at' => null,
                ],
                metadata: ['billing_period' => $plan->billing_period],
            );
        });
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): TenantSubscription
    {
        return DB::transaction(fn (): TenantSubscription => $this->changePlanWithinTransaction($tenant, $newPlan));
    }

    public function hasEligibleSubscription(Tenant $tenant): bool
    {
        if (! $tenant->isActive()) {
            return false;
        }

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($subscription === null) {
            return false;
        }

        $status = $subscription->getAttribute('status');
        $periodEndsAt = $subscription->getAttribute('current_period_ends_at');

        return $status instanceof SubscriptionStatus
            && in_array($status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)
            && $periodEndsAt instanceof CarbonInterface
            && $periodEndsAt->isFuture();
    }

    public function assertCanRequestPlanChange(Tenant $tenant, Plan $newPlan): void
    {
        if (! $newPlan->exists || ! $newPlan->is_active) {
            throw new DomainException('The requested plan is not available.');
        }

        if (! $this->hasEligibleSubscription($tenant)) {
            throw new DomainException('The tenant subscription is not eligible for a plan change.');
        }

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        if ((int) $subscription->plan_id === (int) $newPlan->id) {
            throw new DomainException('The tenant is already on this plan.');
        }

        $duplicatePending = PlanChangeRequest::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('requested_plan_id', $newPlan->id)
            ->where('status', PlanChangeRequestStatus::Pending)
            ->exists();

        if ($duplicatePending) {
            throw new DomainException('A request for this plan is already pending.');
        }
    }

    public function approvePlanChange(PlanChangeRequest $request, ?User $actor = null): TenantSubscription
    {
        $subscription = DB::transaction(function () use ($request, $actor): TenantSubscription {
            $this->assertPlatformActor($actor);

            $lockedRequest = PlanChangeRequest::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isPendingPlanChangeRequest($lockedRequest)) {
                throw new DomainException('Only pending plan-change requests can be approved.');
            }

            $tenant = Tenant::query()->findOrFail((int) $lockedRequest->tenant_id);
            $plan = Plan::query()
                ->whereKey($lockedRequest->requested_plan_id)
                ->where('is_active', true)
                ->first();

            if ($plan === null) {
                throw new DomainException('The requested plan is no longer available.');
            }

            if (! $this->hasEligibleSubscription($tenant)) {
                throw new DomainException('The tenant subscription is no longer eligible for a plan change.');
            }

            $tenancy = app(Tenancy::class);
            $tenancy->set($tenant);

            try {
                $subscription = $this->changePlanWithinTransaction($tenant, $plan, $actor);
                $lockedRequest->update([
                    'status' => PlanChangeRequestStatus::Approved,
                    'reviewed_by_user_id' => $actor?->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ]);
                $this->logPlanChangeAction($lockedRequest, $tenant, $plan, 'approved', $actor);

                return $subscription;
            } finally {
                $tenancy->set(null);
            }
        });

        $this->notifyOwnerOfPlanChangeDecision($request, true);

        return $subscription;
    }

    public function rejectPlanChange(PlanChangeRequest $request, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($request, $actor, $reason): void {
            $this->assertPlatformActor($actor);

            if (! is_string($reason) || trim($reason) === '') {
                throw new DomainException('A rejection reason is required.');
            }

            $lockedRequest = PlanChangeRequest::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isPendingPlanChangeRequest($lockedRequest)) {
                throw new DomainException('Only pending plan-change requests can be rejected.');
            }

            $tenant = Tenant::query()->findOrFail((int) $lockedRequest->tenant_id);
            $tenancy = app(Tenancy::class);
            $tenancy->set($tenant);

            try {
                $lockedRequest->update([
                    'status' => PlanChangeRequestStatus::Rejected,
                    'reviewed_by_user_id' => $actor?->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => trim($reason),
                ]);
                $this->logPlanChangeAction($lockedRequest, $tenant, null, 'rejected', $actor);
            } finally {
                $tenancy->set(null);
            }
        });

        $this->notifyOwnerOfPlanChangeDecision($request, false);
    }

    public function processExpirations(): int
    {
        $expired = 0;

        Tenant::query()->whereIn('status', ['trial', 'active'])->each(function (Tenant $tenant) use (&$expired): void {
            app(Tenancy::class)->set($tenant);

            $periodEnded = DB::transaction(function () use ($tenant): bool {
                $subscription = TenantSubscription::query()
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->first();

                if ($subscription === null) {
                    return false;
                }

                $periodEndsAt = $subscription->getAttribute('current_period_ends_at');

                if (! $periodEndsAt instanceof CarbonInterface || $periodEndsAt->isFuture()) {
                    return false;
                }

                $plan = Plan::query()->find($subscription->plan_id);

                $this->applySubscriptionChange(
                    $tenant,
                    SubscriptionEventType::Expired,
                    $plan,
                    null,
                    ['status' => SubscriptionStatus::Expired],
                    metadata: ['reason' => 'period_ended'],
                );

                $owner = User::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->first();
                $owner?->notify(new SubscriptionExpiredNotification($tenant));

                return true;
            });

            if ($periodEnded) {
                $expired++;
            }
        });

        return $expired;
    }

    public function canCreateProduct(Tenant $tenant): bool
    {
        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->first();
        $limit = $subscription?->entitlement('max_products');

        return $limit === null || Product::query()->count() < (int) $limit;
    }

    public function canAddStaff(Tenant $tenant): bool
    {
        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->first();
        $limit = $subscription?->entitlement('max_staff');

        return $limit === null || User::query()->where('tenant_id', $tenant->id)->where('role', 'staff')->count() < (int) $limit;
    }

    public function canUseCustomDomain(Tenant $tenant): bool
    {
        if (! $this->hasEligibleSubscription($tenant)) {
            return false;
        }

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $subscription) {
            return false;
        }

        if ($subscription->getAttribute('custom_domain_allowed') !== null) {
            return $subscription->entitlement('custom_domain_allowed') === true;
        }

        $plan = Plan::query()->find($subscription->plan_id);

        return $plan?->is_active === true
            && $plan->custom_domain_allowed === true;
    }

    /**
     * Directly assigns a plan to a tenant as a Platform Admin support action.
     * The subscription row remains authoritative and every mutation travels
     * through applySubscriptionChange so tenant state and events stay in sync.
     */
    public function assignPlan(Tenant $tenant, Plan $plan, ?User $actor = null, ?string $note = null): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $plan, $actor, $note): TenantSubscription {
            $this->assertPlatformActor($actor);

            if (! $plan->exists || ! $plan->is_active) {
                throw new DomainException('The requested plan is not available.');
            }

            $subscription = TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($subscription !== null && (int) $subscription->plan_id === (int) $plan->id) {
                throw new DomainException('The tenant is already assigned to this plan.');
            }

            $fromPlan = $subscription === null ? null : Plan::query()->find((int) $subscription->plan_id);

            return $this->applySubscriptionChange(
                $tenant,
                SubscriptionEventType::Subscribed,
                $fromPlan,
                $plan,
                [
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'current_period_starts_at' => now(),
                    'current_period_ends_at' => $this->periodEnd($plan),
                    'cancelled_at' => null,
                ],
                actor: $actor,
                note: $note,
                metadata: [
                    'reason' => 'platform_assignment',
                    'billing_period' => $plan->billing_period,
                    ...$this->previousStateMetadata($subscription),
                ],
            );
        });
    }

    /**
     * Extends the current subscription period by a number of days as a
     * Platform Admin support action. Extension is based on the later of the
     * current period end and now, never shortens the period, and preserves
     * the subscription status.
     */
    public function extendSubscription(Tenant $tenant, int $days, ?User $actor = null, ?string $note = null): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $days, $actor, $note): TenantSubscription {
            $this->assertPlatformActor($actor);

            if ($days <= 0) {
                throw new DomainException('Extension days must be a positive number.');
            }

            $subscription = TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $subscription->getAttribute('status');

            if (! $status instanceof SubscriptionStatus
                || in_array($status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
                throw new DomainException('The subscription cannot be extended in its current state.');
            }

            $currentEnd = $subscription->getAttribute('current_period_ends_at');
            $base = $currentEnd instanceof CarbonInterface && $currentEnd->isFuture() ? $currentEnd : now();
            $plan = Plan::query()->find((int) $subscription->plan_id);

            return $this->applySubscriptionChange(
                $tenant,
                SubscriptionEventType::Renewed,
                $plan,
                null,
                ['current_period_ends_at' => $base->copy()->addDays($days)],
                actor: $actor,
                note: $note,
                metadata: [
                    'reason' => 'platform_extension',
                    'extension_days' => $days,
                    ...$this->previousStateMetadata($subscription),
                ],
            );
        });
    }

    /**
     * Cancels a subscription as a Platform Admin support action. The status
     * becomes Cancelled and the cancellation time is recorded.
     */
    public function cancelSubscription(Tenant $tenant, ?User $actor = null, ?string $note = null): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $actor, $note): TenantSubscription {
            $this->assertPlatformActor($actor);

            $subscription = TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($subscription->getAttribute('status') === SubscriptionStatus::Cancelled) {
                throw new DomainException('The subscription is already cancelled.');
            }

            $plan = Plan::query()->find((int) $subscription->plan_id);

            return $this->applySubscriptionChange(
                $tenant,
                SubscriptionEventType::Cancelled,
                $plan,
                null,
                [
                    'status' => SubscriptionStatus::Cancelled,
                    'cancelled_at' => now(),
                ],
                actor: $actor,
                note: $note,
                metadata: [
                    'reason' => 'platform_cancellation',
                    ...$this->previousStateMetadata($subscription),
                ],
            );
        });
    }

    /**
     * Reactivates a cancelled or expired subscription as a Platform Admin
     * support action. The subscription resumes as Active with a fresh period
     * and the entitlement snapshot is refreshed from the current plan.
     */
    public function reactivateSubscription(Tenant $tenant, ?User $actor = null, ?string $note = null): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $actor, $note): TenantSubscription {
            $this->assertPlatformActor($actor);

            $subscription = TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $subscription->getAttribute('status');

            if (! $status instanceof SubscriptionStatus
                || ! in_array($status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
                throw new DomainException('Only cancelled or expired subscriptions can be reactivated.');
            }

            $plan = Plan::query()
                ->whereKey((int) $subscription->plan_id)
                ->where('is_active', true)
                ->first();

            if ($plan === null) {
                throw new DomainException('The subscription plan is no longer available and cannot be reactivated.');
            }

            return $this->applySubscriptionChange(
                $tenant,
                SubscriptionEventType::Reactivated,
                $plan,
                $plan,
                [
                    'status' => SubscriptionStatus::Active,
                    'current_period_starts_at' => now(),
                    'current_period_ends_at' => $this->periodEnd($plan),
                    'cancelled_at' => null,
                ],
                actor: $actor,
                note: $note,
                metadata: [
                    'reason' => 'platform_reactivation',
                    'billing_period' => $plan->billing_period,
                    ...$this->previousStateMetadata($subscription),
                ],
            );
        });
    }

    /**
     * Platform Admin + SaaS guard, exposed for other platform services
     * (e.g. subscription payments) that must enforce the same actor policy.
     */
    public function assertCanManageSubscriptions(User $actor): void
    {
        $this->assertPlatformActor($actor);
    }

    private function logEvent(
        Tenant $tenant,
        SubscriptionEventType $type,
        ?Plan $from,
        ?Plan $to,
        ?string $note = null,
        ?User $actor = null,
        ?Carbon $effectiveAt = null,
        array $metadata = [],
    ): void {
        SubscriptionEvent::query()->create([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'from_plan_id' => $from?->id,
            'to_plan_id' => $to?->id,
            'note' => $note,
            'actor_user_id' => $actor?->id,
            'effective_at' => $effectiveAt ?? now(),
            'metadata' => $metadata,
        ]);
    }

    private function changePlanWithinTransaction(Tenant $tenant, Plan $newPlan, ?User $actor = null): TenantSubscription
    {
        if (! $newPlan->is_active) {
            throw new DomainException('The requested plan is not available.');
        }

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->lockForUpdate()
            ->firstOrFail();
        $oldPlan = Plan::query()->findOrFail($subscription->plan_id);

        if ((int) $oldPlan->id === (int) $newPlan->id) {
            throw new DomainException('The tenant is already on this plan.');
        }

        $eventType = $newPlan->price > $oldPlan->price ? SubscriptionEventType::Upgraded : SubscriptionEventType::Downgraded;

        return $this->applySubscriptionChange(
            $tenant,
            $eventType,
            $oldPlan,
            $newPlan,
            [
                'plan_id' => $newPlan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => $this->periodEnd($newPlan),
            ],
            actor: $actor,
            metadata: [
                'billing_period' => $newPlan->billing_period,
                'previous_status' => $subscription->getAttribute('status') instanceof SubscriptionStatus
                    ? $subscription->getAttribute('status')->value
                    : (string) $subscription->getAttribute('status'),
                'previous_period_ends_at' => $subscription->getAttribute('current_period_ends_at') instanceof CarbonInterface
                    ? $subscription->getAttribute('current_period_ends_at')->toISOString()
                    : null,
            ],
        );
    }

    /**
     * Single authoritative internal subscription mutation path. Atomically
     * writes the subscription row, synchronizes the denormalized
     * tenants.status / tenants.plan fields, and appends one immutable
     * SubscriptionEvent with effective_at, optional actor and metadata.
     * The TenantSubscription row remains the authoritative state.
     */
    private function applySubscriptionChange(
        Tenant $tenant,
        SubscriptionEventType $eventType,
        ?Plan $fromPlan,
        ?Plan $toPlan,
        array $subscriptionAttributes,
        ?User $actor = null,
        ?string $note = null,
        array $metadata = [],
    ): TenantSubscription {
        $tenantId = (int) $tenant->getKey();

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if ($subscription === null) {
            $subscription = new TenantSubscription(['tenant_id' => $tenantId]);
        }

        if ($toPlan !== null) {
            $subscriptionAttributes['plan_name'] = $toPlan->name;
            $subscriptionAttributes['billing_period'] = $toPlan->billing_period;
            $subscriptionAttributes['price'] = $toPlan->price;
            $subscriptionAttributes['max_products'] = $toPlan->max_products;
            $subscriptionAttributes['max_staff'] = $toPlan->max_staff;
            $subscriptionAttributes['custom_domain_allowed'] = $toPlan->custom_domain_allowed;
        }

        $subscription->fill($subscriptionAttributes)->save();

        $this->syncTenantState($tenant, $subscription);

        $this->logEvent($tenant, $eventType, $fromPlan, $toPlan, $note, $actor, null, $metadata);

        return $subscription->refresh();
    }

    /**
     * Billing-period end for a plan. Monthly plans renew after one month,
     * yearly plans after one year. Trials are unaffected and stay on the
     * configured trial_days window (see startTrial).
     */
    private function periodEnd(Plan $plan, ?Carbon $from = null): Carbon
    {
        $from ??= now();

        return $plan->billing_period === 'yearly'
            ? $from->copy()->addYear()
            : $from->copy()->addMonth();
    }

    private function syncTenantState(Tenant $tenant, TenantSubscription $subscription): void
    {
        $status = $subscription->getAttribute('status');
        $plan = Plan::query()->find((int) $subscription->getAttribute('plan_id'));

        $tenant->forceFill([
            'status' => $status instanceof SubscriptionStatus ? $this->tenantStatusFor($status) : (string) $status,
            'plan' => $plan?->slug,
        ])->save();
    }

    private function tenantStatusFor(SubscriptionStatus $status): string
    {
        return match ($status) {
            SubscriptionStatus::Trialing => 'trial',
            SubscriptionStatus::Active => 'active',
            SubscriptionStatus::PastDue => 'past_due',
            SubscriptionStatus::Cancelled => 'cancelled',
            SubscriptionStatus::Expired => 'expired',
        };
    }

    private function notifyOwnerOfPlanChangeDecision(PlanChangeRequest $request, bool $approved): void
    {
        $tenantId = (int) $request->getAttribute('tenant_id');
        $owner = User::query()->where('tenant_id', $tenantId)->where('role', 'owner')->first();

        if ($owner === null) {
            return;
        }

        $notification = $approved
            ? new PlanChangeApprovedNotification($request)
            : new PlanChangeRejectedNotification($request);

        $owner->notify($notification);
    }

    private function logPlanChangeAction(
        PlanChangeRequest $request,
        Tenant $tenant,
        ?Plan $plan,
        string $action,
        ?User $actor,
    ): void {
        $activity = activity('subscriptions')
            ->performedOn($request)
            ->event('plan_change.'.$action)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'request_id' => $request->id,
                'requested_plan_id' => $plan === null ? $request->requested_plan_id : $plan->id,
            ]);

        if ($actor !== null) {
            $activity->causedBy($actor);
        } else {
            $activity->causedByAnonymous();
        }

        $activity->log('plan_change.'.$action);
    }

    private function isPendingPlanChangeRequest(PlanChangeRequest $request): bool
    {
        $status = $request->getAttribute('status');

        return $status instanceof PlanChangeRequestStatus
            && $status === PlanChangeRequestStatus::Pending;
    }

    private function assertPlatformActor(?User $actor): void
    {
        if ($actor === null
            || config('deployment.mode') !== DeploymentMode::SaaS->value
            || ! $actor->is_platform_admin
            || ! $actor->is_active) {
            throw new DomainException('Only an active Platform Admin can manage subscriptions.');
        }
    }

    /**
     * @return array{previous_status?: string, previous_period_ends_at?: string|null}
     */
    private function previousStateMetadata(?TenantSubscription $subscription): array
    {
        if ($subscription === null) {
            return [];
        }

        return [
            'previous_status' => $subscription->getAttribute('status') instanceof SubscriptionStatus
                ? $subscription->getAttribute('status')->value
                : (string) $subscription->getAttribute('status'),
            'previous_period_ends_at' => $subscription->getAttribute('current_period_ends_at') instanceof CarbonInterface
                ? $subscription->getAttribute('current_period_ends_at')->toISOString()
                : null,
        ];
    }
}
