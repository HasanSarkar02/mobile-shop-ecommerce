<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Location;
use App\Models\NotificationTemplate;
use App\Models\StoreSetting;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Notifications\WelcomeTenantOwnerNotification;
use App\Services\SubscriptionService;
use App\Services\TenantBootstrapService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedBootstrapPlans();
});

it('bootstraps a trial tenant with owner, subscription and initial store data', function (): void {
    Notification::fake();

    [$tenant, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Trial Shop',
        'subdomain' => 'TrialShop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim Karim', 'email' => 'Rahim@Example.com', 'password' => 'secret1234'],
    ]);

    expect($tenant->subdomain)->toBe('trialshop')
        ->and($tenant->status)->toBe('trial')
        ->and($tenant->plan)->toBe('trial');

    expect($owner->tenant_id)->toBe($tenant->id)
        ->and($owner->role)->toBe('owner')
        ->and($owner->email)->toBe('rahim@example.com')
        ->and($owner->is_platform_admin)->not->toBeTrue();

    $subscription = $tenant->subscription;
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->plan->slug)->toBe('trial');

    app(Tenancy::class)->set($tenant);

    expect(Location::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreThemeSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(NotificationTemplate::query()->where('tenant_id', $tenant->id)->count())->toBe(11)
        ->and($tenant->domains()->count())->toBe(0);

    Notification::assertSentTo($owner, WelcomeTenantOwnerNotification::class);
    Notification::assertNotSentTo($owner, TenantOwnerInvitationNotification::class);
});

it('bootstraps a paid plan tenant as active with an active subscription and invitation', function (): void {
    Notification::fake();

    [$tenant, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Starter Shop',
        'subdomain' => 'startershop',
        'plan' => 'starter',
        'owner' => ['name' => 'Karim Rahim', 'email' => 'karim@example.com'],
    ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION);

    expect($tenant->status)->toBe('active')
        ->and($tenant->plan)->toBe('starter');

    $subscription = $tenant->subscription;
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan->slug)->toBe('starter')
        ->and($subscription->current_period_ends_at->isFuture())->toBeTrue();

    app(Tenancy::class)->set($tenant);

    expect(Location::query()->where('tenant_id', $tenant->id)->count())->toBe(1);

    Notification::assertSentTo($owner, TenantOwnerInvitationNotification::class);
});

it('creates an unusable password hash and setup token for invited owners', function (): void {
    Notification::fake();

    [$tenant, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Invite Shop',
        'subdomain' => 'inviteshop',
        'plan' => 'growth',
        'owner' => ['name' => 'Sadia', 'email' => 'sadia@example.com'],
    ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION);

    expect($owner->password)->not->toBeNull()
        ->and(Hash::needsRehash($owner->password))->toBeFalse()
        ->and($owner->tenant_id)->toBe($tenant->id);

    Notification::assertSentTo($owner, TenantOwnerInvitationNotification::class, function (TenantOwnerInvitationNotification $notification): bool {
        expect(strlen($notification->setupToken))->toBe(64)
            ->and($notification->expiresAt->isFuture())->toBeTrue();

        return true;
    });
});

it('makes a paid tenant custom-domain entitled and a trial tenant not', function (): void {
    [$starter, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Entitled Shop',
        'subdomain' => 'entitledshop',
        'plan' => 'starter',
        'owner' => ['name' => 'Karim', 'email' => 'karim-entitled@example.com'],
    ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION);

    [$trial, $trialOwner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Trial Only Shop',
        'subdomain' => 'trialonly',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'rahim-trial@example.com', 'password' => 'secret1234'],
    ]);

    expect(app(SubscriptionService::class)->canUseCustomDomain($starter))->toBeTrue()
        ->and(app(SubscriptionService::class)->canUseCustomDomain($trial))->toBeFalse()
        ->and($starter->domains()->count())->toBe(0)
        ->and($trial->domains()->count())->toBe(0);
});

it('rolls back the whole bootstrap when owner creation fails', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $tenantCount = Tenant::query()->count();

    expect(fn () => app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Rollback Shop',
        'subdomain' => 'rollbackshop',
        'plan' => 'trial',
        'owner' => ['name' => 'X', 'email' => 'taken@example.com', 'password' => 'secret1234'],
    ]))->toThrow(ValidationException::class);

    expect(Tenant::query()->count())->toBe($tenantCount)
        ->and(Tenant::query()->where('subdomain', 'rollbackshop')->exists())->toBeFalse()
        ->and(Location::query()->withoutGlobalScope('tenant')->where('tenant_id', Tenant::query()->max('id'))->count())->toBe(0)
        ->and(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});

it('rejects an unavailable plan', function (): void {
    expect(fn () => app(TenantBootstrapService::class)->bootstrap([
        'name' => 'No Plan Shop',
        'subdomain' => 'noplanshop',
        'plan' => 'enterprise',
        'owner' => ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret1234'],
    ]))->toThrow(ValidationException::class);

    expect(Tenant::query()->where('subdomain', 'noplanshop')->exists())->toBeFalse();
});

it('never attaches an owner to a different tenant', function (): void {
    Notification::fake();

    [$tenantA, $ownerA] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Shop A', 'subdomain' => 'shop-a', 'plan' => 'trial',
        'owner' => ['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret1234'],
    ]);
    [$tenantB, $ownerB] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Shop B', 'subdomain' => 'shop-b', 'plan' => 'trial',
        'owner' => ['name' => 'B', 'email' => 'b@example.com', 'password' => 'secret1234'],
    ]);

    expect($ownerA->tenant_id)->toBe($tenantA->id)
        ->and($ownerB->tenant_id)->toBe($tenantB->id)
        ->and($ownerA->tenant_id)->not->toBe($tenantB->id);
});
