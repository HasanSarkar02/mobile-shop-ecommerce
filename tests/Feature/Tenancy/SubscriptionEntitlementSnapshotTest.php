<?php

declare(strict_types=1);

use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\PlanResource;
use App\Filament\Platform\Resources\PlanResource\Pages\EditPlan;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    seedBootstrapPlans();
    Filament::setCurrentPanel('platform');
    app(Tenancy::class)->set(null);
});

function c4Plan(array $overrides = []): Plan
{
    return Plan::query()->create(array_merge([
        'name' => 'C4 Plan '.Str::random(8),
        'slug' => 'c4-'.Str::lower(Str::random(8)),
        'price' => 1000,
        'billing_period' => 'monthly',
        'max_products' => 50,
        'max_staff' => 2,
        'custom_domain_allowed' => false,
        'is_active' => true,
        'sort_order' => 1,
    ], $overrides));
}

function c4Tenant(): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c4-'.Str::lower(Str::random(8)),
        'status' => 'trial',
        'plan' => 'trial',
    ]);
}

function c4LegacySubscription(Tenant $tenant, Plan $plan): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
}

function c4Admin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

it('backfills entitlement snapshots for existing subscriptions', function (): void {
    $tenant = c4Tenant();
    $plan = c4Plan([
        'name' => 'Backfill Plan',
        'price' => 199000,
        'billing_period' => 'yearly',
        'max_products' => 120,
        'max_staff' => 8,
        'custom_domain_allowed' => true,
    ]);
    c4LegacySubscription($tenant, $plan);

    DB::statement(
        'UPDATE tenant_subscriptions AS ts
         JOIN plans AS p ON p.id = ts.plan_id
         SET ts.plan_name = p.name,
             ts.billing_period = p.billing_period,
             ts.price = p.price,
             ts.max_products = p.max_products,
             ts.max_staff = p.max_staff,
             ts.custom_domain_allowed = p.custom_domain_allowed',
    );

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($subscription->plan_name)->toBe('Backfill Plan')
        ->and($subscription->billing_period)->toBe('yearly')
        ->and($subscription->price)->toBe(199000)
        ->and($subscription->max_products)->toBe(120)
        ->and($subscription->max_staff)->toBe(8)
        ->and($subscription->custom_domain_allowed)->toBeTrue();
});

it('aborts the snapshot migration when a subscription references a missing plan', function (): void {
    $tenant = c4Tenant();

    try {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => 999999,
            'status' => SubscriptionStatus::Active,
            'current_period_starts_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    $dangling = TenantSubscription::query()
        ->whereNotNull('plan_id')
        ->whereNotIn('plan_id', Plan::query()->select('id'))
        ->exists();

    expect($dangling)->toBeTrue();

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($subscription->plan_name)->toBeNull()
        ->and($subscription->max_products)->toBeNull();
});

it('startTrial captures the entitlement snapshot from the trial plan', function (): void {
    $tenant = c4Tenant();
    $trial = Plan::query()->where('slug', 'trial')->firstOrFail();

    $subscription = app(SubscriptionService::class)->startTrial($tenant, $trial, 14)->fresh();

    expect($subscription->plan_name)->toBe('Free Trial')
        ->and($subscription->billing_period)->toBe('monthly')
        ->and($subscription->price)->toBe(0)
        ->and($subscription->max_products)->toBe(50)
        ->and($subscription->max_staff)->toBe(2)
        ->and($subscription->custom_domain_allowed)->toBeFalse();
});

it('activatePlan captures the entitlement snapshot from the chosen plan', function (): void {
    $tenant = c4Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    $subscription = app(SubscriptionService::class)->activatePlan($tenant, $starter)->fresh();

    expect($subscription->plan_name)->toBe('Starter')
        ->and($subscription->price)->toBe(99000)
        ->and($subscription->max_products)->toBe(500)
        ->and($subscription->max_staff)->toBe(5)
        ->and($subscription->custom_domain_allowed)->toBeTrue();
});

it('changePlan refreshes the entitlement snapshot to the new plan', function (): void {
    $tenant = c4Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $growth = Plan::query()->where('slug', 'growth')->firstOrFail();
    app(SubscriptionService::class)->activatePlan($tenant, $starter);

    app(SubscriptionService::class)->changePlan($tenant, $growth);

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($subscription->plan_name)->toBe('Growth')
        ->and($subscription->max_products)->toBeNull()
        ->and($subscription->max_staff)->toBeNull()
        ->and($subscription->custom_domain_allowed)->toBeTrue();
});

it('keeps custom-domain entitlement from the snapshot when the plan changes', function (): void {
    $tenant = c4Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    app(SubscriptionService::class)->activatePlan($tenant, $starter);

    $starter->update(['custom_domain_allowed' => false]);
    expect(app(SubscriptionService::class)->canUseCustomDomain($tenant))->toBeTrue();

    $starter->update(['custom_domain_allowed' => true, 'is_active' => false]);
    expect(app(SubscriptionService::class)->canUseCustomDomain($tenant))->toBeTrue();

    app(SubscriptionService::class)->changePlan($tenant, Plan::query()->where('slug', 'trial')->firstOrFail());
    expect(app(SubscriptionService::class)->canUseCustomDomain($tenant))->toBeFalse();
});

it('enforces the snapshotted product quota even if the plan expands', function (): void {
    $tenant = c4Tenant();
    $plan = c4Plan(['max_products' => 0]);
    app(SubscriptionService::class)->activatePlan($tenant, $plan);
    app(Tenancy::class)->set($tenant);

    $plan->update(['max_products' => 100]);

    expect(app(SubscriptionService::class)->canCreateProduct($tenant))->toBeFalse();
});

it('enforces the snapshotted staff quota even if the plan expands', function (): void {
    $tenant = c4Tenant();
    $plan = c4Plan(['max_staff' => 1]);
    app(SubscriptionService::class)->activatePlan($tenant, $plan);

    $plan->update(['max_staff' => 100]);
    expect(app(SubscriptionService::class)->canAddStaff($tenant))->toBeTrue();

    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);
    expect(app(SubscriptionService::class)->canAddStaff($tenant))->toBeFalse();
});

it('falls back to the catalog plan quota for legacy subscriptions without a snapshot', function (): void {
    $tenant = c4Tenant();
    $plan = c4Plan(['max_products' => 50, 'max_staff' => 5, 'custom_domain_allowed' => true]);
    c4LegacySubscription($tenant, $plan);
    app(Tenancy::class)->set($tenant);

    expect(app(SubscriptionService::class)->canCreateProduct($tenant))->toBeTrue()
        ->and(app(SubscriptionService::class)->canAddStaff($tenant))->toBeTrue()
        ->and(app(SubscriptionService::class)->canUseCustomDomain($tenant))->toBeTrue();
});

it('processExpirations does not recalculate the entitlement snapshot', function (): void {
    $tenant = c4Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    app(SubscriptionService::class)->activatePlan($tenant, $starter);
    TenantSubscription::query()->where('tenant_id', $tenant->id)->update(['current_period_ends_at' => now()->subMinute()]);

    $starter->update(['max_products' => 10, 'custom_domain_allowed' => false]);
    $starter->update(['is_active' => false]);

    app(SubscriptionService::class)->processExpirations();

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($subscription->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->plan_name)->toBe('Starter')
        ->and($subscription->max_products)->toBe(500)
        ->and($subscription->custom_domain_allowed)->toBeTrue();
});

it('keeps the snapshotted quota when the catalog plan is shrunk', function (): void {
    $tenant = c4Tenant();
    $plan = c4Plan(['max_products' => 500, 'max_staff' => 5]);
    app(SubscriptionService::class)->activatePlan($tenant, $plan);
    app(Tenancy::class)->set($tenant);

    $plan->update(['max_products' => 50, 'max_staff' => 1]);

    expect(app(SubscriptionService::class)->canCreateProduct($tenant))->toBeTrue()
        ->and(app(SubscriptionService::class)->canAddStaff($tenant))->toBeTrue();
});

it('keeps custom-domain entitlement from the snapshot when the plan is deactivated', function (): void {
    $tenant = c4Tenant();
    $plan = c4Plan(['custom_domain_allowed' => true]);
    app(SubscriptionService::class)->activatePlan($tenant, $plan);

    $plan->update(['is_active' => false]);

    expect(app(SubscriptionService::class)->canUseCustomDomain($tenant))->toBeTrue();
});

it('keeps entitlement snapshots independent across tenants on the same plan', function (): void {
    $tenantA = c4Tenant();
    $tenantB = c4Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $growth = Plan::query()->where('slug', 'growth')->firstOrFail();
    app(SubscriptionService::class)->activatePlan($tenantA, $starter);
    app(SubscriptionService::class)->activatePlan($tenantB, $starter);

    app(SubscriptionService::class)->changePlan($tenantA, $growth);

    $subscriptionA = TenantSubscription::query()->where('tenant_id', $tenantA->id)->firstOrFail();
    $subscriptionB = TenantSubscription::query()->where('tenant_id', $tenantB->id)->firstOrFail();
    expect($subscriptionA->plan_name)->toBe('Growth')
        ->and($subscriptionB->plan_name)->toBe('Starter')
        ->and($subscriptionB->max_products)->toBe(500);
});

it('blocks deleting a referenced plan with a friendly notification', function (): void {
    $admin = c4Admin();
    $tenant = c4Tenant();
    $plan = c4Plan();
    c4LegacySubscription($tenant, $plan);

    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);
    PlanChangeRequest::query()->create([
        'requested_plan_id' => $plan->id,
        'status' => PlanChangeRequestStatus::Pending,
    ]);
    $tenancy->set(null);

    expect(PlanResource::isPlanReferenced($plan))->toBeTrue();

    Auth::guard('platform')->login($admin);
    Livewire::test(EditPlan::class, ['record' => $plan->id])
        ->callAction('delete');

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeTrue();
    Auth::guard('platform')->logout();
});

it('allows deleting an unreferenced plan', function (): void {
    $admin = c4Admin();
    $plan = c4Plan();

    expect(PlanResource::isPlanReferenced($plan))->toBeFalse();

    Auth::guard('platform')->login($admin);
    Livewire::test(EditPlan::class, ['record' => $plan->id])
        ->callAction('delete');

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeFalse();
    Auth::guard('platform')->logout();
});

it('audits plan create, update and delete with actor and changed attributes', function (): void {
    $admin = c4Admin();
    Auth::guard('platform')->login($admin);

    $plan = c4Plan(['name' => 'Audited Plan']);
    $created = Activity::query()
        ->where('log_name', 'plans')
        ->where('subject_type', Plan::class)
        ->where('subject_id', $plan->id)
        ->where('event', 'plan.created')
        ->latest()
        ->first();
    expect($created)->not->toBeNull()
        ->and($created->causer_id)->toBe($admin->id)
        ->and($created->properties->get('plan_name'))->toBe('Audited Plan');

    $plan->update(['max_products' => 250]);
    $updated = Activity::query()
        ->where('log_name', 'plans')
        ->where('subject_type', Plan::class)
        ->where('subject_id', $plan->id)
        ->where('event', 'plan.updated')
        ->latest()
        ->first();
    expect($updated)->not->toBeNull()
        ->and($updated->causer_id)->toBe($admin->id)
        ->and($updated->properties->get('changed'))->toHaveKey('max_products');

    $plan->delete();
    $deleted = Activity::query()
        ->where('log_name', 'plans')
        ->where('subject_type', Plan::class)
        ->where('subject_id', $plan->id)
        ->where('event', 'plan.deleted')
        ->latest()
        ->first();
    expect($deleted)->not->toBeNull()
        ->and($deleted->causer_id)->toBe($admin->id);

    Auth::guard('platform')->logout();
});

it('keeps the C1 monthly billing window while capturing the snapshot', function (): void {
    $tenant = c4Tenant();
    $plan = c4Plan(['billing_period' => 'monthly', 'price' => 99000]);

    $subscription = app(SubscriptionService::class)->activatePlan($tenant, $plan)->fresh();

    expect($subscription->current_period_ends_at->between(now()->copy()->addDays(27), now()->copy()->addDays(32)))->toBeTrue()
        ->and($subscription->billing_period)->toBe('monthly')
        ->and($subscription->price)->toBe(99000)
        ->and($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe($plan->slug);
});

it('keeps the A1 plan-change approval atomic and refreshes the snapshot', function (): void {
    $tenant = c4Tenant();
    $current = c4Plan(['price' => 1000]);
    $requested = c4Plan(['price' => 2000]);
    c4LegacySubscription($tenant, $current);
    $admin = c4Admin();

    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);
    $request = PlanChangeRequest::query()->create([
        'requested_plan_id' => $requested->id,
        'status' => PlanChangeRequestStatus::Pending,
    ]);
    $tenancy->set(null);

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($subscription->plan_id)->toBe($requested->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_name)->toBe($requested->name)
        ->and($subscription->price)->toBe(2000)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Approved)
        ->and($tenant->refresh()->status)->toBe('active');
});
