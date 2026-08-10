<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Single gateway for subscription lifecycle and plan-based quota enforcement.
 */
class SubscriptionService
{
    public function startTrial(Tenant $tenant, Plan $trialPlan, int $trialDays): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $trialPlan, $trialDays): TenantSubscription {
            $subscription = TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $trialPlan->id,
                'status' => SubscriptionStatus::Trialing,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addDays($trialDays),
            ]);

            $this->logEvent($tenant, SubscriptionEventType::TrialStarted, null, $trialPlan);

            return $subscription;
        });
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $newPlan): TenantSubscription {
            $subscription = $tenant->subscription;
            $oldPlan = $subscription->plan;

            $eventType = $newPlan->price > $oldPlan->price ? SubscriptionEventType::Upgraded : SubscriptionEventType::Downgraded;

            $subscription->update([
                'plan_id' => $newPlan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addMonth(),
            ]);

            $tenant->update(['status' => 'active', 'plan' => $newPlan->slug]);

            $this->logEvent($tenant, $eventType, $oldPlan, $newPlan);

            return $subscription->fresh();
        });
    }

    public function processExpirations(): int
    {
        $expired = 0;

        Tenant::query()->whereIn('status', ['trial', 'active'])->each(function (Tenant $tenant) use (&$expired): void {
            app(Tenancy::class)->set($tenant);

            $subscription = $tenant->subscription;

            if (! $subscription || $subscription->current_period_ends_at->isFuture()) {
                return;
            }

            $subscription->update(['status' => SubscriptionStatus::Expired]);
            $tenant->update(['status' => 'expired']);

            $this->logEvent($tenant, SubscriptionEventType::Expired, $subscription->plan, null);

            $owner = User::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->first();
            $owner?->notify(new SubscriptionExpiredNotification($tenant));

            $expired++;
        });

        return $expired;
    }

    public function canCreateProduct(Tenant $tenant): bool
    {
        $limit = $tenant->subscription?->plan?->max_products;

        return $limit === null || Product::query()->count() < $limit;
    }

    public function canAddStaff(Tenant $tenant): bool
    {
        $limit = $tenant->subscription?->plan?->max_staff;

        return $limit === null || User::query()->where('tenant_id', $tenant->id)->where('role', 'staff')->count() < $limit;
    }

    private function logEvent(Tenant $tenant, SubscriptionEventType $type, ?Plan $from, ?Plan $to, ?string $note = null): void
    {
        \App\Models\SubscriptionEvent::query()->create([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'from_plan_id' => $from?->id,
            'to_plan_id' => $to?->id,
            'note' => $note,
        ]);
    }
}