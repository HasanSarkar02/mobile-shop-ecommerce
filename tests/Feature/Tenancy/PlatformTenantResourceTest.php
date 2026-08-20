<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Platform\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Platform\Resources\TenantResource\Pages\ViewTenant;
use App\Models\Domain;
use App\Models\Location;
use App\Models\NotificationTemplate;
use App\Models\Plan;
use App\Models\StoreSetting;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Services\OwnerInvitationService;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
    seedBootstrapPlans();
});

function platformTenantAdmin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

it('creates a fully bootstrapped tenant from the platform create form', function (): void {
    Notification::fake();
    Auth::guard('platform')->login(platformTenantAdmin());

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Platform Shop',
            'subdomain' => 'platformshop',
            'plan' => 'starter',
            'owner_name' => 'Rahim Karim',
            'owner_email' => 'owner@platformshop.test',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tenant = Tenant::query()->where('subdomain', 'platformshop')->firstOrFail();

    expect($tenant->status)->toBe('active')
        ->and($tenant->plan)->toBe('starter');

    $owner = User::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->firstOrFail();
    expect($owner->email)->toBe('owner@platformshop.test')
        ->and($owner->tenant_id)->toBe($tenant->id)
        ->and($owner->is_platform_admin)->not->toBeTrue();

    $invitation = TenantInvitation::query()->where('tenant_id', $tenant->id)->where('user_id', $owner->id)->firstOrFail();
    expect($invitation->token_digest)->toHaveLength(64)
        ->and($invitation->delivery_status)->toBe(TenantInvitation::DELIVERY_QUEUED);

    $subscription = $tenant->subscription;
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan->slug)->toBe('starter');

    app(Tenancy::class)->set($tenant);

    expect(Location::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreThemeSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(NotificationTemplate::query()->where('tenant_id', $tenant->id)->count())->toBe(11)
        ->and($tenant->domains()->count())->toBe(0);

    Notification::assertSentTo($owner, TenantOwnerInvitationNotification::class);

    Auth::guard('platform')->logout();
});

it('creates a trial tenant with a trial subscription from the platform create form', function (): void {
    Notification::fake();
    Auth::guard('platform')->login(platformTenantAdmin());

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Trial Platform Shop',
            'subdomain' => 'trialplatformshop',
            'plan' => 'trial',
            'owner_name' => 'Rahim Karim',
            'owner_email' => 'trialowner@example.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tenant = Tenant::query()->where('subdomain', 'trialplatformshop')->firstOrFail();

    expect($tenant->status)->toBe('trial');

    $subscription = $tenant->subscription;
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->plan->slug)->toBe('trial');

    Auth::guard('platform')->logout();
});

it('rejects invalid subdomains using ValidSubdomain', function (): void {
    Auth::guard('platform')->login(platformTenantAdmin());

    foreach (['bad_sub', '-lead', 'trail-', 'sh', 'my shop', 'admin'] as $invalid) {
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Bad Shop',
                'subdomain' => $invalid,
                'plan' => 'trial',
                'owner_name' => 'Rahim Karim',
                'owner_email' => 'bad'.fake()->unique()->numberBetween(1, 99999).'@example.com',
            ])
            ->call('create')
            ->assertHasFormErrors(['subdomain']);
    }

    Auth::guard('platform')->logout();
});

it('rejects duplicate subdomains', function (): void {
    Tenant::query()->create(['name' => 'Existing', 'subdomain' => 'existing', 'status' => 'active']);
    Auth::guard('platform')->login(platformTenantAdmin());

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Duplicate Shop',
            'subdomain' => 'existing',
            'plan' => 'trial',
            'owner_name' => 'Rahim Karim',
            'owner_email' => 'dup@example.com',
        ])
        ->call('create')
        ->assertHasFormErrors(['subdomain']);

    Auth::guard('platform')->logout();
});

it('rejects an owner email that already exists', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    Auth::guard('platform')->login(platformTenantAdmin());

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Taken Shop',
            'subdomain' => 'takenshop',
            'plan' => 'trial',
            'owner_name' => 'Rahim Karim',
            'owner_email' => 'taken@example.com',
        ])
        ->call('create')
        ->assertHasFormErrors(['owner_email']);

    expect(Tenant::query()->where('subdomain', 'takenshop')->exists())->toBeFalse();

    Auth::guard('platform')->logout();
});

it('exposes the current active plans instead of legacy free and paid options', function (): void {
    Auth::guard('platform')->login(platformTenantAdmin());

    Livewire::test(CreateTenant::class)
        ->assertFormFieldExists('plan', checkFieldUsing: function ($field): bool {
            return array_keys($field->getOptions()) === ['trial', 'starter', 'growth'];
        });

    Auth::guard('platform')->logout();
});

it('renders a read-only tenant detail page with authoritative tenant operations data', function (): void {
    $admin = platformTenantAdmin();
    $tenant = Tenant::factory()->create([
        'name' => 'Detail Tenant',
        'subdomain' => 'detail-tenant',
        'status' => 'active',
        'plan' => 'trial',
        'contact_email' => 'contact@detail-tenant.test',
        'contact_phone' => '01700000000',
    ]);
    $plan = Plan::query()->where('slug', 'growth')->firstOrFail();
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
        'name' => 'Detail Owner',
        'email' => 'owner@detail-tenant.test',
        'email_verified_at' => now(),
    ]);
    $primary = Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'detail-tenant.example.test',
        'normalized_domain' => 'detail-tenant.example.test',
        'status' => DomainStatus::Active,
        'verified_at' => now(),
    ]);
    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'pending.detail-tenant.example.test',
        'normalized_domain' => 'pending.detail-tenant.example.test',
        'status' => DomainStatus::Pending,
    ]);
    Tenant::query()->whereKey($tenant->id)->update(['primary_domain_id' => $primary->id]);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewTenant::class, ['record' => $tenant->getRouteKey()])
        ->assertSee('Detail Tenant')
        ->assertSee('detail-tenant')
        ->assertSee('contact@detail-tenant.test')
        ->assertSee('Growth')
        ->assertSee('Active')
        ->assertSee('detail-tenant.example.test')
        ->assertSee('Detail Owner')
        ->assertSee('owner@detail-tenant.test')
        ->assertSee('Total domains')
        ->assertSee('Active custom domains')
        ->assertSee('Pending verification')
        ->assertActionDoesNotExist('delete')
        ->assertActionDoesNotExist('edit');

    Auth::guard('platform')->logout();
});

it('denies Tenant detail access to non-platform users and Dedicated mode', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $admin = platformTenantAdmin();
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);

    Auth::guard('platform')->login($staff);
    expect(TenantResource::canViewAny())
        ->toBeFalse()
        ->and(TenantResource::canView($tenant))->toBeFalse();

    Auth::guard('platform')->login($admin);
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(TenantResource::canViewAny())
        ->toBeFalse()
        ->and(TenantResource::canView($tenant))->toBeFalse();

    Auth::guard('platform')->logout();
});

it('shows Platform Admin invitation recovery actions for a pending owner invitation', function (): void {
    $admin = platformTenantAdmin();
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);
    app(OwnerInvitationService::class)->issue($tenant, $owner, invitedBy: $admin);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewTenant::class, ['record' => $tenant->getRouteKey()])
        ->assertActionExists('resendOwnerInvitation')
        ->assertActionExists('revokeOwnerInvitation');

    Auth::guard('platform')->logout();
});

it('restricts platform panel access to platform admins only', function (): void {
    $admin = platformTenantAdmin();
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);

    expect($admin->canAccessPanel(Filament::getPanel('platform')))->toBeTrue()
        ->and($staff->canAccessPanel(Filament::getPanel('platform')))->toBeFalse();
});
