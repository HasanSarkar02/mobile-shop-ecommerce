<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Platform\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Store\Pages\BillingPage;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Tenancy\Tenancy;
use App\Support\Tenancy\TenantContextResolver;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
    app(Tenancy::class)->set(null);
});

function a1Plan(string $prefix, int $price, bool $active = true): Plan
{
    return Plan::query()->create([
        'name' => $prefix.' '.Str::random(8),
        'slug' => Str::lower($prefix.'-'.Str::random(8)),
        'price' => $price,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => true,
        'is_active' => $active,
        'sort_order' => 1,
    ]);
}

/** @return array{tenant: Tenant, plan: Plan, subscription: TenantSubscription} */
function a1SubscriptionFixture(
    string $tenantStatus = 'active',
    SubscriptionStatus $subscriptionStatus = SubscriptionStatus::Active,
    ?Plan $plan = null,
    ?CarbonInterface $periodEndsAt = null,
): array {
    $tenant = Tenant::factory()->create([
        'subdomain' => 'a1-'.Str::lower(Str::random(8)),
        'status' => $tenantStatus,
    ]);
    $plan ??= a1Plan('A1 Plan', 1000);
    $subscription = TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => $subscriptionStatus,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => $periodEndsAt ?? now()->addMonth(),
    ]);

    return compact('tenant', 'plan', 'subscription');
}

function a1PlanRequest(Tenant $tenant, Plan $plan, PlanChangeRequestStatus $status = PlanChangeRequestStatus::Pending): PlanChangeRequest
{
    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);

    try {
        return PlanChangeRequest::query()->create([
            'requested_plan_id' => $plan->id,
            'status' => $status,
        ]);
    } finally {
        $tenancy->set(null);
    }
}

it('blocks SaaS subdomains when the subscription is expired even if tenant status is active', function (): void {
    ['tenant' => $tenant] = a1SubscriptionFixture(
        subscriptionStatus: SubscriptionStatus::Expired,
        periodEndsAt: now()->subMinute(),
    );
    $tenant->update(['status' => 'active']);

    expect(app(TenantContextResolver::class)->resolve($tenant->subdomain.'.'.config('tenancy.central_domain')))
        ->toBeNull();
});

it('blocks cancelled and past-due SaaS subscriptions', function (): void {
    foreach ([SubscriptionStatus::Cancelled, SubscriptionStatus::PastDue] as $status) {
        ['tenant' => $tenant] = a1SubscriptionFixture(subscriptionStatus: $status);
        $tenant->update(['status' => 'active']);

        expect(app(TenantContextResolver::class)->resolve($tenant->subdomain.'.'.config('tenancy.central_domain')))
            ->toBeNull();
    }
});

it('blocks an ineligible active subscription whose period has ended', function (): void {
    ['tenant' => $tenant] = a1SubscriptionFixture(
        subscriptionStatus: SubscriptionStatus::Active,
        periodEndsAt: now()->subMinute(),
    );
    $tenant->update(['status' => 'active']);

    expect(app(TenantContextResolver::class)->resolve($tenant->subdomain.'.'.config('tenancy.central_domain')))
        ->toBeNull();
});

it('allows eligible active and trial tenants on SaaS subdomains', function (): void {
    ['tenant' => $activeTenant] = a1SubscriptionFixture();
    ['tenant' => $trialTenant] = a1SubscriptionFixture(
        tenantStatus: 'trial',
        subscriptionStatus: SubscriptionStatus::Trialing,
    );
    $resolver = app(TenantContextResolver::class);

    expect($resolver->resolve($activeTenant->subdomain.'.'.config('tenancy.central_domain'))?->is($activeTenant))->toBeTrue()
        ->and($resolver->resolve($trialTenant->subdomain.'.'.config('tenancy.central_domain'))?->is($trialTenant))->toBeTrue();
});

it('keeps Dedicated mode resolution independent of subscription eligibility', function (): void {
    ['tenant' => $tenant] = a1SubscriptionFixture(
        subscriptionStatus: SubscriptionStatus::Expired,
        periodEndsAt: now()->subMinute(),
    );
    $tenant->update(['status' => 'active']);
    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'dedicated.example.test',
    ]);

    expect(app(TenantContextResolver::class)->resolve('dedicated.example.test')?->is($tenant))->toBeTrue()
        ->and(app(TenantContextResolver::class)->resolve('other.example.test'))->toBeNull();
});

it('does not expose a destructive delete action and keeps tenant status read-only', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    Auth::guard('platform')->login($admin);

    Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
        ->assertActionDoesNotExist('delete')
        ->assertFormFieldDisabled('status');

    expect(TenantResource::getPages())->not->toHaveKey('delete');

    Auth::guard('platform')->logout();
});

it('approves a valid pending plan request atomically', function (): void {
    $currentPlan = a1Plan('Current Plan', 1000);
    $requestedPlan = a1Plan('Requested Plan', 2000);
    ['tenant' => $tenant, 'subscription' => $subscription] = a1SubscriptionFixture(plan: $currentPlan);
    $request = a1PlanRequest($tenant, $requestedPlan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    expect($subscription->fresh()->plan_id)->toBe($requestedPlan->id)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Approved);
});

it('rolls back the subscription when request approval fails after the subscription mutation', function (): void {
    $currentPlan = a1Plan('Rollback Current', 1000);
    $requestedPlan = a1Plan('Rollback Requested', 2000);
    ['tenant' => $tenant, 'subscription' => $subscription] = a1SubscriptionFixture(plan: $currentPlan);
    $request = a1PlanRequest($tenant, $requestedPlan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    $shouldFail = true;

    DB::listen(function ($query) use (&$shouldFail): void {
        if ($shouldFail
            && str_contains(strtolower($query->sql), 'update')
            && str_contains(strtolower($query->sql), 'plan_change_requests')) {
            $shouldFail = false;
            throw new RuntimeException('Simulated request status write failure.');
        }
    });

    expect(fn () => app(SubscriptionService::class)->approvePlanChange($request, $admin))
        ->toThrow(RuntimeException::class);

    expect($subscription->fresh()->plan_id)->toBe($currentPlan->id)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Pending);
});

it('locks the request and rejects a second approval safely', function (): void {
    $currentPlan = a1Plan('Lock Current', 1000);
    $requestedPlan = a1Plan('Lock Requested', 2000);
    ['tenant' => $tenant] = a1SubscriptionFixture(plan: $currentPlan);
    $request = a1PlanRequest($tenant, $requestedPlan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    expect(fn () => app(SubscriptionService::class)->approvePlanChange($request, $admin))
        ->toThrow(DomainException::class);

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'plan_change_requests') && str_contains($sql, 'for update')))
        ->toBeTrue();
});

it('rejects stale, inactive, and same-plan requests', function (): void {
    $currentPlan = a1Plan('Validation Current', 1000);
    $requestedPlan = a1Plan('Validation Requested', 2000);
    $inactivePlan = a1Plan('Validation Inactive', 3000, active: false);
    ['tenant' => $tenant, 'subscription' => $subscription] = a1SubscriptionFixture(plan: $currentPlan);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    $service = app(SubscriptionService::class);

    expect(fn () => $service->assertCanRequestPlanChange($tenant, $currentPlan))
        ->toThrow(DomainException::class);

    $pending = a1PlanRequest($tenant, $requestedPlan);

    expect(fn () => $service->assertCanRequestPlanChange($tenant, $requestedPlan))
        ->toThrow(DomainException::class);

    $service->approvePlanChange($pending, $admin);

    $inactiveRequest = a1PlanRequest($tenant, $inactivePlan);
    $inactiveSubscriptionPlan = $subscription->fresh()->plan_id;

    expect(fn () => $service->approvePlanChange($inactiveRequest, $admin))
        ->toThrow(DomainException::class);

    $staleRequest = a1PlanRequest($tenant, $inactivePlan, PlanChangeRequestStatus::Rejected);

    expect(fn () => $service->approvePlanChange($staleRequest, $admin))
        ->toThrow(DomainException::class)
        ->and($subscription->fresh()->plan_id)->toBe($inactiveSubscriptionPlan);
});

it('prevents duplicate pending requests without a schema change', function (): void {
    $currentPlan = a1Plan('Duplicate Current', 1000);
    $requestedPlan = a1Plan('Duplicate Requested', 2000);
    ['tenant' => $tenant] = a1SubscriptionFixture(plan: $currentPlan);
    a1PlanRequest($tenant, $requestedPlan);

    expect(fn () => app(SubscriptionService::class)->assertCanRequestPlanChange($tenant, $requestedPlan))
        ->toThrow(DomainException::class);
});

it('rejects duplicate and inactive plan requests through the Billing page', function (): void {
    $currentPlan = a1Plan('Billing Current', 1000);
    $requestedPlan = a1Plan('Billing Requested', 2000);
    $inactivePlan = a1Plan('Billing Inactive', 3000, active: false);
    ['tenant' => $tenant] = a1SubscriptionFixture(plan: $currentPlan);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);
    Auth::guard('web')->login($owner);
    Filament::setCurrentPanel('store');

    Livewire::test(BillingPage::class)
        ->call('requestPlan', $requestedPlan->id)
        ->call('requestPlan', $requestedPlan->id)
        ->call('requestPlan', $inactivePlan->id);

    expect(PlanChangeRequest::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())
        ->toBe(1);

    Auth::guard('web')->logout();
    $tenancy->set(null);
});
