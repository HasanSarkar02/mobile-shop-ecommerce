<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Services\SubscriptionService;
use App\Services\TenantBootstrapService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedBootstrapPlans();
    app(Tenancy::class)->set(null);
});

function c1Plan(string $prefix, int $price, string $billingPeriod = 'monthly', bool $active = true): Plan
{
    return Plan::query()->create([
        'name' => $prefix.' '.Str::random(8),
        'slug' => Str::lower($prefix.'-'.Str::random(8)),
        'price' => $price,
        'billing_period' => $billingPeriod,
        'custom_domain_allowed' => true,
        'is_active' => $active,
        'sort_order' => 1,
    ]);
}

function c1Tenant(string $status = 'trial'): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c1-'.Str::lower(Str::random(8)),
        'status' => $status,
        'plan' => 'trial',
    ]);
}

function c1Subscription(Tenant $tenant, Plan $plan): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
}

/** @return Collection<int, SubscriptionEvent> */
function c1Events(Tenant $tenant): Collection
{
    return SubscriptionEvent::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->get();
}

it('startTrial writes consistent tenant and subscription state', function (): void {
    $tenant = c1Tenant();
    $trialPlan = Plan::query()->where('slug', 'trial')->firstOrFail();

    $subscription = app(SubscriptionService::class)->startTrial($tenant, $trialPlan, 14);

    $subscription = $subscription->fresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->plan_id)->toBe($trialPlan->id)
        ->and($subscription->current_period_starts_at->isToday())->toBeTrue()
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(13), now()->copy()->addDays(15)))->toBeTrue();

    expect($tenant->refresh()->status)->toBe('trial')
        ->and($tenant->refresh()->plan)->toBe('trial');

    $event = c1Events($tenant)->first();
    expect($event->type)->toBe(SubscriptionEventType::TrialStarted)
        ->and($event->from_plan_id)->toBeNull()
        ->and($event->to_plan_id)->toBe($trialPlan->id)
        ->and($event->effective_at)->not->toBeNull()
        ->and($event->actor_user_id)->toBeNull()
        ->and($event->metadata['trial_days'])->toBe(14);
});

it('activatePlan sets a monthly billing period of one month', function (): void {
    $tenant = c1Tenant();
    $monthly = c1Plan('Activate Monthly', 99000, 'monthly');

    $subscription = app(SubscriptionService::class)->activatePlan($tenant, $monthly)->fresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_starts_at->isToday())->toBeTrue()
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(27), now()->copy()->addDays(32)))->toBeTrue();

    expect($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe($monthly->slug);
});

it('activatePlan sets a yearly billing period of one year', function (): void {
    $tenant = c1Tenant();
    $yearly = c1Plan('Activate Yearly', 990000, 'yearly');

    $subscription = app(SubscriptionService::class)->activatePlan($tenant, $yearly)->fresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(364), now()->copy()->addDays(367)))->toBeTrue();
});

it('changePlan sets a monthly billing period of one month', function (): void {
    $tenant = c1Tenant();
    $current = c1Plan('Change Current Monthly', 1000, 'monthly');
    $new = c1Plan('Change New Monthly', 2000, 'monthly');
    c1Subscription($tenant, $current);

    $subscription = app(SubscriptionService::class)->changePlan($tenant, $new)->fresh();

    expect($subscription->plan_id)->toBe($new->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_starts_at->isToday())->toBeTrue()
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(27), now()->copy()->addDays(32)))->toBeTrue();

    expect($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe($new->slug);
});

it('changePlan sets a yearly billing period of one year', function (): void {
    $tenant = c1Tenant();
    $current = c1Plan('Change Current Yearly', 1000, 'monthly');
    $new = c1Plan('Change New Yearly', 2000, 'yearly');
    c1Subscription($tenant, $current);

    $subscription = app(SubscriptionService::class)->changePlan($tenant, $new)->fresh();

    expect($subscription->plan_id)->toBe($new->id)
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(364), now()->copy()->addDays(367)))->toBeTrue();

    expect($tenant->refresh()->plan)->toBe($new->slug);
});

it('keeps TenantSubscription and Tenant synchronized across every mutation path', function (): void {
    $service = app(SubscriptionService::class);
    $tenant = c1Tenant();
    $trialPlan = Plan::query()->where('slug', 'trial')->firstOrFail();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    $service->startTrial($tenant, $trialPlan, 14);
    expect($tenant->refresh()->status)->toBe('trial')
        ->and($tenant->refresh()->plan)->toBe('trial')
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->id)->first()->status)->toBe(SubscriptionStatus::Trialing);

    $service->changePlan($tenant, $starter);
    expect($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe('starter')
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->id)->first()->status)->toBe(SubscriptionStatus::Active);

    TenantSubscription::query()->where('tenant_id', $tenant->id)->update(['current_period_ends_at' => now()->subDay()]);
    $service->processExpirations();

    expect($tenant->refresh()->status)->toBe('expired')
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->id)->first()->status)->toBe(SubscriptionStatus::Expired);
});

it('records enriched subscription events with effective_at and platform actor', function (): void {
    $current = c1Plan('Event Current', 1000);
    $requested = c1Plan('Event Requested', 2000);
    $tenant = c1Tenant();
    c1Subscription($tenant, $current);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);
    $request = PlanChangeRequest::query()->create([
        'requested_plan_id' => $requested->id,
        'status' => PlanChangeRequestStatus::Pending,
    ]);
    $tenancy->set(null);

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    $event = c1Events($tenant)->firstWhere('type', SubscriptionEventType::Upgraded);
    expect($event)->not->toBeNull()
        ->and($event->from_plan_id)->toBe($current->id)
        ->and($event->to_plan_id)->toBe($requested->id)
        ->and($event->effective_at)->not->toBeNull()
        ->and($event->effective_at->isPast())->toBeTrue()
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->metadata['billing_period'])->toBe('monthly')
        ->and($event->metadata['previous_status'])->toBe('active');
});

it('expires subscriptions through the scheduler consistently', function (): void {
    Notification::fake();
    $plan = c1Plan('Expire Plan', 1000);
    $tenant = c1Tenant('active');
    $subscription = c1Subscription($tenant, $plan);
    $subscription->update(['current_period_ends_at' => now()->subMinute()]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $count = app(SubscriptionService::class)->processExpirations();

    expect($count)->toBe(1);

    expect($tenant->refresh()->status)->toBe('expired')
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->id)->first()->status)->toBe(SubscriptionStatus::Expired);

    $event = c1Events($tenant)->first();
    expect($event->type)->toBe(SubscriptionEventType::Expired)
        ->and($event->from_plan_id)->toBe($plan->id)
        ->and($event->to_plan_id)->toBeNull()
        ->and($event->actor_user_id)->toBeNull()
        ->and($event->effective_at)->not->toBeNull()
        ->and($event->metadata['reason'])->toBe('period_ended');

    Notification::assertSentTo($owner, SubscriptionExpiredNotification::class);
});

it('rolls back the whole mutation when a later write fails', function (): void {
    $tenant = c1Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    $shouldFail = true;
    DB::listen(function ($query) use (&$shouldFail): void {
        if ($shouldFail
            && str_contains(strtolower($query->sql), 'update')
            && str_contains(strtolower($query->sql), 'tenants')) {
            $shouldFail = false;
            throw new RuntimeException('Simulated tenant write failure.');
        }
    });

    expect(fn () => app(SubscriptionService::class)->activatePlan($tenant, $starter))
        ->toThrow(RuntimeException::class);

    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and($tenant->refresh()->status)->toBe('trial')
        ->and(c1Events($tenant)->count())->toBe(0);
});

it('keeps the public bootstrap and signup paths working', function (): void {
    Notification::fake();

    [$tenant, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'C1 Shop',
        'subdomain' => 'c1shop',
        'plan' => 'starter',
        'owner' => ['name' => 'C1 Owner', 'email' => 'c1-owner@example.com'],
    ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION);

    expect($tenant->status)->toBe('active')
        ->and($tenant->plan)->toBe('starter');

    $subscription = $tenant->subscription;
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan->slug)->toBe('starter')
        ->and($subscription->current_period_ends_at->isFuture())->toBeTrue()
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(27), now()->copy()->addDays(32)))->toBeTrue();

    $event = c1Events($tenant)->first();
    expect($event->type)->toBe(SubscriptionEventType::Subscribed)
        ->and($event->actor_user_id)->toBeNull()
        ->and($event->effective_at)->not->toBeNull();

    Notification::assertSentTo($owner, TenantOwnerInvitationNotification::class);
});

it('keeps A1 plan-request approval atomic through the same write path', function (): void {
    $current = c1Plan('A1 Current', 1000);
    $requested = c1Plan('A1 Requested', 2000);
    $tenant = c1Tenant();
    $subscription = c1Subscription($tenant, $current);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);
    $request = PlanChangeRequest::query()->create([
        'requested_plan_id' => $requested->id,
        'status' => PlanChangeRequestStatus::Pending,
    ]);
    $tenancy->set(null);

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    expect($subscription->fresh()->plan_id)->toBe($requested->id)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Approved)
        ->and($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe($requested->slug);

    $upgraded = c1Events($tenant)->firstWhere('type', SubscriptionEventType::Upgraded);
    expect($upgraded)->not->toBeNull()
        ->and($upgraded->actor_user_id)->toBe($admin->id)
        ->and($upgraded->effective_at)->not->toBeNull()
        ->and($upgraded->metadata['previous_status'])->toBe('active');
});

it('assignPlan creates a fresh subscription for a tenant without one', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Assign Fresh', 150000, 'monthly');
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $subscription = app(SubscriptionService::class)->assignPlan($tenant, $plan, $admin)->fresh();

    expect($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_starts_at->isToday())->toBeTrue()
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(27), now()->copy()->addDays(32)))->toBeTrue()
        ->and($subscription->cancelled_at)->toBeNull()
        ->and($subscription->entitlement('plan_name'))->toBe($plan->name)
        ->and($subscription->entitlement('max_products'))->toBe($plan->max_products);

    expect($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe($plan->slug);

    $event = c1Events($tenant)->first();
    expect($event->type)->toBe(SubscriptionEventType::Subscribed)
        ->and($event->from_plan_id)->toBeNull()
        ->and($event->to_plan_id)->toBe($plan->id)
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->effective_at)->not->toBeNull()
        ->and($event->metadata['reason'])->toBe('platform_assignment');
});

it('assignPlan reassigns an existing subscription and refreshes the snapshot', function (): void {
    $tenant = c1Tenant();
    $current = c1Plan('Assign Current', 1000);
    $new = c1Plan('Assign New', 300000, 'yearly');
    c1Subscription($tenant, $current);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $subscription = app(SubscriptionService::class)->assignPlan($tenant, $new, $admin)->fresh();

    expect($subscription->plan_id)->toBe($new->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_ends_at->between(now()->copy()->addDays(364), now()->copy()->addDays(367)))->toBeTrue()
        ->and($subscription->entitlement('billing_period'))->toBe('yearly')
        ->and($subscription->entitlement('price'))->toBe($new->price);

    expect($tenant->refresh()->plan)->toBe($new->slug);

    $event = c1Events($tenant)->firstWhere('type', SubscriptionEventType::Subscribed);
    expect($event)->not->toBeNull()
        ->and($event->from_plan_id)->toBe($current->id)
        ->and($event->to_plan_id)->toBe($new->id)
        ->and($event->metadata['previous_status'])->toBe('active')
        ->and($event->metadata['previous_period_ends_at'])->not->toBeNull();
});

it('assignPlan records the platform actor, note, and audit metadata', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Assign Note', 120000);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    app(SubscriptionService::class)->assignPlan($tenant, $plan, $admin, 'Support override after data migration.');

    $event = c1Events($tenant)->first();
    expect($event->actor_user_id)->toBe($admin->id)
        ->and($event->note)->toBe('Support override after data migration.')
        ->and($event->metadata['billing_period'])->toBe('monthly');
});

it('assignPlan rejects inactive plans', function (): void {
    $tenant = c1Tenant();
    $inactive = c1Plan('Assign Inactive', 1000, 'monthly', active: false);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    expect(fn () => app(SubscriptionService::class)->assignPlan($tenant, $inactive, $admin))
        ->toThrow(DomainException::class, 'not available');

    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(c1Events($tenant)->count())->toBe(0);
});

it('assignPlan rejects reassigning the current plan', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Assign Same', 1000);
    c1Subscription($tenant, $plan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    expect(fn () => app(SubscriptionService::class)->assignPlan($tenant, $plan, $admin))
        ->toThrow(DomainException::class, 'already assigned');

    expect(c1Events($tenant)->count())->toBe(0);
});

it('assignPlan requires an active platform admin actor', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Assign Guard', 1000);
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);
    $inactiveAdmin = User::factory()->create(['is_platform_admin' => true, 'is_active' => false, 'app_authentication_secret' => 'test-secret']);

    expect(fn () => app(SubscriptionService::class)->assignPlan($tenant, $plan, null))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->assignPlan($tenant, $plan, $staff))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->assignPlan($tenant, $plan, $inactiveAdmin))->toThrow(DomainException::class, 'Platform Admin');

    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(c1Events($tenant)->count())->toBe(0);
});

it('extendSubscription extends from the current period end', function (): void {
    $tenant = c1Tenant('active');
    $plan = c1Plan('Extend Future', 1000);
    $subscription = c1Subscription($tenant, $plan);
    $originalEnd = now()->addDays(20);
    $subscription->update(['current_period_ends_at' => $originalEnd]);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $result = app(SubscriptionService::class)->extendSubscription($tenant, 30, $admin)->fresh();

    expect($result->status)->toBe(SubscriptionStatus::Active)
        ->and($result->current_period_ends_at->toDateString())->toBe($originalEnd->copy()->addDays(30)->toDateString());

    $event = c1Events($tenant)->first();
    expect($event->type)->toBe(SubscriptionEventType::Renewed)
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->metadata['reason'])->toBe('platform_extension')
        ->and($event->metadata['extension_days'])->toBe(30)
        ->and($event->metadata['previous_period_ends_at'])->not->toBeNull();
});

it('extendSubscription extends from now when the period already ended', function (): void {
    $tenant = c1Tenant('active');
    $plan = c1Plan('Extend Past', 1000);
    $subscription = c1Subscription($tenant, $plan);
    $subscription->update(['current_period_ends_at' => now()->subDay()]);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $result = app(SubscriptionService::class)->extendSubscription($tenant, 14, $admin)->fresh();

    expect($result->current_period_ends_at->between(now()->copy()->addDays(13), now()->copy()->addDays(15)))->toBeTrue()
        ->and($result->current_period_ends_at->isFuture())->toBeTrue();
});

it('extendSubscription rejects non-positive day counts', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Extend Zero', 1000);
    c1Subscription($tenant, $plan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    expect(fn () => app(SubscriptionService::class)->extendSubscription($tenant, 0, $admin))->toThrow(DomainException::class, 'positive');
    expect(fn () => app(SubscriptionService::class)->extendSubscription($tenant, -5, $admin))->toThrow(DomainException::class, 'positive');
});

it('extendSubscription rejects cancelled or expired subscriptions', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Extend Terminal', 1000);
    $subscription = c1Subscription($tenant, $plan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $subscription->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
    expect(fn () => app(SubscriptionService::class)->extendSubscription($tenant, 30, $admin))->toThrow(DomainException::class, 'current state');

    $subscription->update(['status' => SubscriptionStatus::Expired]);
    expect(fn () => app(SubscriptionService::class)->extendSubscription($tenant, 30, $admin))->toThrow(DomainException::class, 'current state');
});

it('cancelSubscription cancels and synchronizes tenant state', function (): void {
    $tenant = c1Tenant('active');
    $plan = c1Plan('Cancel Plan', 1000);
    c1Subscription($tenant, $plan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $result = app(SubscriptionService::class)->cancelSubscription($tenant, $admin, 'Customer requested closure.')->fresh();

    expect($result->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($result->cancelled_at)->not->toBeNull()
        ->and($tenant->refresh()->status)->toBe('cancelled')
        ->and($tenant->refresh()->plan)->toBe($plan->slug);

    $event = c1Events($tenant)->first();
    expect($event->type)->toBe(SubscriptionEventType::Cancelled)
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->note)->toBe('Customer requested closure.')
        ->and($event->metadata['reason'])->toBe('platform_cancellation')
        ->and($event->metadata['previous_status'])->toBe('active');
});

it('cancelSubscription rejects already cancelled subscriptions', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Cancel Twice', 1000);
    c1Subscription($tenant, $plan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    app(SubscriptionService::class)->cancelSubscription($tenant, $admin);

    expect(fn () => app(SubscriptionService::class)->cancelSubscription($tenant, $admin))
        ->toThrow(DomainException::class, 'already cancelled');

    expect(c1Events($tenant)->count())->toBe(1);
});

it('reactivateSubscription restores a cancelled subscription with a fresh period and snapshot', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Reactivate', 200000, 'yearly');
    $subscription = c1Subscription($tenant, $plan);
    $subscription->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $result = app(SubscriptionService::class)->reactivateSubscription($tenant, $admin)->fresh();

    expect($result->status)->toBe(SubscriptionStatus::Active)
        ->and($result->cancelled_at)->toBeNull()
        ->and($result->current_period_starts_at->isToday())->toBeTrue()
        ->and($result->current_period_ends_at->between(now()->copy()->addDays(364), now()->copy()->addDays(367)))->toBeTrue()
        ->and($result->entitlement('billing_period'))->toBe('yearly')
        ->and($tenant->refresh()->status)->toBe('active');

    $event = c1Events($tenant)->firstWhere('type', SubscriptionEventType::Reactivated);
    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->to_plan_id)->toBe($plan->id)
        ->and($event->metadata['reason'])->toBe('platform_reactivation')
        ->and($event->metadata['previous_status'])->toBe('cancelled');
});

it('reactivateSubscription restores an expired subscription', function (): void {
    $tenant = c1Tenant('expired');
    $plan = c1Plan('Reactivate Expired', 1000);
    $subscription = c1Subscription($tenant, $plan);
    $subscription->update(['status' => SubscriptionStatus::Expired, 'current_period_ends_at' => now()->subDay()]);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    $result = app(SubscriptionService::class)->reactivateSubscription($tenant, $admin)->fresh();

    expect($result->status)->toBe(SubscriptionStatus::Active)
        ->and($result->current_period_ends_at->isFuture())->toBeTrue()
        ->and($tenant->refresh()->status)->toBe('active');

    expect(c1Events($tenant)->firstWhere('type', SubscriptionEventType::Reactivated))->not->toBeNull();
});

it('reactivateSubscription rejects subscriptions that are not cancelled or expired', function (): void {
    $tenant = c1Tenant('active');
    $plan = c1Plan('Reactivate Active', 1000);
    c1Subscription($tenant, $plan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    expect(fn () => app(SubscriptionService::class)->reactivateSubscription($tenant, $admin))
        ->toThrow(DomainException::class, 'cancelled or expired');
});

it('reactivateSubscription rejects a subscription whose plan is no longer active', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Reactivate Gone', 1000);
    $subscription = c1Subscription($tenant, $plan);
    $subscription->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
    $plan->update(['is_active' => false]);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    expect(fn () => app(SubscriptionService::class)->reactivateSubscription($tenant, $admin))
        ->toThrow(DomainException::class, 'no longer available');
});

it('blocks every C2 operation in Dedicated mode and for non-admin actors', function (): void {
    $tenant = c1Tenant();
    $plan = c1Plan('Blocked Ops', 1000);
    c1Subscription($tenant, $plan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);

    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(fn () => app(SubscriptionService::class)->assignPlan($tenant, $plan, $admin))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->extendSubscription($tenant, 30, $admin))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->cancelSubscription($tenant, $admin))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->reactivateSubscription($tenant, $admin))->toThrow(DomainException::class, 'Platform Admin');

    config()->set('deployment.mode', DeploymentMode::SaaS->value);

    expect(fn () => app(SubscriptionService::class)->assignPlan($tenant, $plan, $staff))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->extendSubscription($tenant, 30, $staff))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->cancelSubscription($tenant, $staff))->toThrow(DomainException::class, 'Platform Admin');
    expect(fn () => app(SubscriptionService::class)->reactivateSubscription($tenant, $staff))->toThrow(DomainException::class, 'Platform Admin');

    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->first()->status)->toBe(SubscriptionStatus::Active)
        ->and(c1Events($tenant)->count())->toBe(0);
});
