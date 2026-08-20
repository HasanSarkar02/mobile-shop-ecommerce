<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\TenantSubscriptionResource;
use App\Filament\Platform\Resources\TenantSubscriptionResource\Pages\ListTenantSubscriptions;
use App\Filament\Platform\Resources\TenantSubscriptionResource\Pages\ViewTenantSubscription;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
    seedBootstrapPlans();
});

function platformSubscriptionAdmin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function platformSubscriptionFor(Tenant $tenant, Plan $plan, string $status = 'active'): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::from($status),
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
}

it('lists subscriptions across tenants with working filters', function (): void {
    Auth::guard('platform')->login(platformSubscriptionAdmin());
    $tenantA = Tenant::factory()->create(['name' => 'Alpha Shop', 'subdomain' => 'alpha-shop', 'status' => 'active']);
    $tenantB = Tenant::factory()->create(['name' => 'Beta Shop', 'subdomain' => 'beta-shop', 'status' => 'trial']);
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $growth = Plan::query()->where('slug', 'growth')->firstOrFail();
    $subA = platformSubscriptionFor($tenantA, $starter);
    $subB = platformSubscriptionFor($tenantB, $growth, 'trialing');

    Livewire::test(ListTenantSubscriptions::class)
        ->assertCanSeeTableRecords([$subA, $subB])
        ->assertTableColumnStateSet('tenant.name', 'Alpha Shop', $subA)
        ->assertTableColumnStateSet('plan_name', 'Starter', $subA);

    Livewire::test(ListTenantSubscriptions::class)
        ->filterTable('status', 'trialing')
        ->assertCanSeeTableRecords([$subB])
        ->assertCanNotSeeTableRecords([$subA]);

    Auth::guard('platform')->logout();
});

it('assigns a plan to a tenant through the Assign Plan action', function (): void {
    $admin = platformSubscriptionAdmin();
    $tenant = Tenant::factory()->create(['status' => 'trial', 'plan' => 'trial']);
    $trial = Plan::query()->where('slug', 'trial')->firstOrFail();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    platformSubscriptionFor($tenant, $trial, 'trialing');
    Auth::guard('platform')->login($admin);

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();

    Livewire::test(ViewTenantSubscription::class, ['record' => $subscription->getRouteKey()])
        ->callAction('assignPlan', [
            'plan_id' => (string) $starter->id,
            'note' => 'Support assignment.',
        ])
        ->assertHasNoActionErrors();

    $subscription = $subscription->fresh();
    expect($subscription->plan_id)->toBe($starter->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe('starter')
        ->and($subscription->entitlement('plan_name'))->toBe('Starter');

    $event = SubscriptionEvent::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->first();
    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(SubscriptionEventType::Subscribed)
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->note)->toBe('Support assignment.');

    Auth::guard('platform')->logout();
});

it('extends a subscription through the Extend action', function (): void {
    $admin = platformSubscriptionAdmin();
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $subscription = platformSubscriptionFor($tenant, $starter);
    $originalEnd = now()->addDays(20);
    $subscription->update(['current_period_ends_at' => $originalEnd]);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewTenantSubscription::class, ['record' => $subscription->getRouteKey()])
        ->callAction('extendSubscription', ['days' => 30, 'note' => 'Grace extension.'])
        ->assertHasNoActionErrors();

    $subscription = $subscription->fresh();
    expect($subscription->current_period_ends_at->toDateString())->toBe($originalEnd->copy()->addDays(30)->toDateString());

    $event = SubscriptionEvent::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->first();
    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(SubscriptionEventType::Renewed)
        ->and($event->metadata['extension_days'])->toBe(30)
        ->and($event->actor_user_id)->toBe($admin->id);

    Auth::guard('platform')->logout();
});

it('cancels a subscription through the Cancel action', function (): void {
    $admin = platformSubscriptionAdmin();
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $subscription = platformSubscriptionFor($tenant, $starter);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewTenantSubscription::class, ['record' => $subscription->getRouteKey()])
        ->callAction('cancelSubscription', ['note' => 'Cancelled via platform.'])
        ->assertHasNoActionErrors();

    $subscription = $subscription->fresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and($tenant->refresh()->status)->toBe('cancelled');

    Auth::guard('platform')->logout();
});

it('reactivates a cancelled subscription through the Reactivate action', function (): void {
    $admin = platformSubscriptionAdmin();
    $tenant = Tenant::factory()->create(['status' => 'cancelled']);
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $subscription = platformSubscriptionFor($tenant, $starter, 'cancelled');
    $subscription->update(['cancelled_at' => now()]);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewTenantSubscription::class, ['record' => $subscription->getRouteKey()])
        ->callAction('reactivateSubscription', ['note' => 'Restored.'])
        ->assertHasNoActionErrors();

    $subscription = $subscription->fresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->cancelled_at)->toBeNull()
        ->and($tenant->refresh()->status)->toBe('active');

    Auth::guard('platform')->logout();
});

it('denies subscription management to non-platform users and Dedicated mode', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $subscription = platformSubscriptionFor($tenant, $starter);
    $admin = platformSubscriptionAdmin();
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);

    Auth::guard('platform')->login($staff);
    expect(TenantSubscriptionResource::canViewAny())->toBeFalse()
        ->and(TenantSubscriptionResource::canView($subscription))->toBeFalse();

    Auth::guard('platform')->login($admin);
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(TenantSubscriptionResource::canViewAny())->toBeFalse()
        ->and(TenantSubscriptionResource::canView($subscription))->toBeFalse();

    Auth::guard('platform')->logout();
});
