<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\PlanChangeApprovedNotification;
use App\Notifications\PlanChangeRejectedNotification;
use App\Services\SubscriptionService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    app(Tenancy::class)->set(null);
});

function c6Plan(string $prefix, int $price, bool $active = true): Plan
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

function c6Tenant(): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c6-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);
}

function c6Subscription(Tenant $tenant, Plan $plan): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
        'plan_name' => $plan->name,
        'billing_period' => $plan->billing_period,
        'price' => $plan->price,
        'max_products' => $plan->max_products,
        'max_staff' => $plan->max_staff,
        'custom_domain_allowed' => $plan->custom_domain_allowed,
    ]);
}

function c6Admin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function c6Request(Tenant $tenant, Plan $plan, PlanChangeRequestStatus $status = PlanChangeRequestStatus::Pending): PlanChangeRequest
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

it('approves a valid pending plan request through the service', function (): void {
    $current = c6Plan('C6 Current', 1000);
    $requested = c6Plan('C6 Requested', 2000);
    $tenant = c6Tenant();
    $subscription = c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    expect($subscription->fresh()->plan_id)->toBe($requested->id)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Approved);
});

it('records the reviewer and reviewed_at when approving', function (): void {
    $current = c6Plan('C6 Approve Current', 1000);
    $requested = c6Plan('C6 Approve Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    $fresh = $request->fresh();
    expect($fresh->reviewed_by_user_id)->toBe($admin->id)
        ->and($fresh->reviewed_at)->not->toBeNull()
        ->and($fresh->rejection_reason)->toBeNull();
});

it('requires a rejection reason', function (): void {
    $current = c6Plan('C6 Reason Current', 1000);
    $requested = c6Plan('C6 Reason Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();

    expect(fn () => app(SubscriptionService::class)->rejectPlanChange($request, $admin))
        ->toThrow(DomainException::class)
        ->and(fn () => app(SubscriptionService::class)->rejectPlanChange($request, $admin, reason: '  '))
        ->toThrow(DomainException::class)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Pending);
});

it('records reviewer, reviewed_at and reason when rejecting', function (): void {
    $current = c6Plan('C6 Reject Current', 1000);
    $requested = c6Plan('C6 Reject Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();

    app(SubscriptionService::class)->rejectPlanChange($request, $admin, reason: 'Payment method not verified');

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(PlanChangeRequestStatus::Rejected)
        ->and($fresh->reviewed_by_user_id)->toBe($admin->id)
        ->and($fresh->reviewed_at)->not->toBeNull()
        ->and($fresh->rejection_reason)->toBe('Payment method not verified');
});

it('does not mutate the subscription when rejecting', function (): void {
    $current = c6Plan('C6 NoChange Current', 1000);
    $requested = c6Plan('C6 NoChange Requested', 2000);
    $tenant = c6Tenant();
    $subscription = c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();

    app(SubscriptionService::class)->rejectPlanChange($request, $admin, reason: 'Not eligible');

    expect($subscription->fresh()->plan_id)->toBe($current->id)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(SubscriptionEvent::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('notifies the owner when a plan request is approved', function (): void {
    Notification::fake();
    $current = c6Plan('C6 Notify Current', 1000);
    $requested = c6Plan('C6 Notify Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    Notification::assertSentTo($owner, PlanChangeApprovedNotification::class);
});

it('notifies the owner when a plan request is rejected', function (): void {
    Notification::fake();
    $current = c6Plan('C6 RejectNotify Current', 1000);
    $requested = c6Plan('C6 RejectNotify Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    app(SubscriptionService::class)->rejectPlanChange($request, $admin, reason: 'Card on file expired');

    Notification::assertSentTo($owner, PlanChangeRejectedNotification::class);
});

it('prevents approving or rejecting a stale request', function (): void {
    $current = c6Plan('C6 Stale Current', 1000);
    $requested = c6Plan('C6 Stale Requested', 2000);
    $tenant = c6Tenant();
    $subscription = c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    expect(fn () => app(SubscriptionService::class)->approvePlanChange($request, $admin))
        ->toThrow(DomainException::class)
        ->and(fn () => app(SubscriptionService::class)->rejectPlanChange($request, $admin, reason: 'Too late'))
        ->toThrow(DomainException::class);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(PlanChangeRequestStatus::Approved)
        ->and($subscription->fresh()->plan_id)->toBe($requested->id);
});

it('keeps concurrent approval safe through row locking', function (): void {
    $current = c6Plan('C6 Lock Current', 1000);
    $requested = c6Plan('C6 Lock Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    app(SubscriptionService::class)->approvePlanChange($request, $admin);

    expect(fn () => app(SubscriptionService::class)->approvePlanChange($request, $admin))
        ->toThrow(DomainException::class)
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'plan_change_requests') && str_contains($sql, 'for update')))
        ->toBeTrue();
});

it('keeps the A1 atomic rollback intact when a later write fails', function (): void {
    $current = c6Plan('C6 Rollback Current', 1000);
    $requested = c6Plan('C6 Rollback Requested', 2000);
    $tenant = c6Tenant();
    $subscription = c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();
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

    expect($subscription->fresh()->plan_id)->toBe($current->id)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Pending)
        ->and(SubscriptionEvent::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('denies non-platform actors from approving or rejecting', function (): void {
    $current = c6Plan('C6 NonAdmin Current', 1000);
    $requested = c6Plan('C6 NonAdmin Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);

    expect(fn () => app(SubscriptionService::class)->approvePlanChange($request, $staff))
        ->toThrow(DomainException::class)
        ->and(fn () => app(SubscriptionService::class)->rejectPlanChange($request, $staff, reason: 'No'))
        ->toThrow(DomainException::class)
        ->and($request->fresh()->status)->toBe(PlanChangeRequestStatus::Pending);
});

it('denies plan decisions in Dedicated mode', function (): void {
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    $current = c6Plan('C6 Dedicated Current', 1000);
    $requested = c6Plan('C6 Dedicated Requested', 2000);
    $tenant = c6Tenant();
    c6Subscription($tenant, $current);
    $request = c6Request($tenant, $requested);
    $admin = c6Admin();

    expect(fn () => app(SubscriptionService::class)->approvePlanChange($request, $admin))
        ->toThrow(DomainException::class)
        ->and(fn () => app(SubscriptionService::class)->rejectPlanChange($request, $admin, reason: 'No'))
        ->toThrow(DomainException::class);
});
