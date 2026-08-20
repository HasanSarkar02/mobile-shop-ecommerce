<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use App\Support\Tenancy\TenantContextResolver;

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'deployment.dedicated.tenant_id' => null,
        'deployment.dedicated.canonical_host' => null,
        'deployment.allowed_hosts' => [],
        'deployment.force_https' => false,
        'deployment.url_scheme' => 'http',
    ]);

    app(Tenancy::class)->set(null);
});

it('resolves central and tenant hosts in SaaS mode', function (): void {
    $central = config('tenancy.central_domain');
    $tenant = Tenant::factory()->create(['subdomain' => 'phase-one-shop']);
    $plan = Plan::query()->create([
        'name' => 'Deployment SaaS Plan',
        'slug' => 'deployment-saas-plan',
        'price' => 1000,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => true,
        'is_active' => true,
    ]);
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
    $resolver = app(TenantContextResolver::class);

    expect($resolver->resolve($central))->toBeNull()
        ->and($resolver->resolve('phase-one-shop.'.$central)?->is($tenant))->toBeTrue()
        ->and($resolver->isCentralHost($central))->toBeTrue();
});

it('resolves only the configured tenant and host in Dedicated mode', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'dedicated-shop']);

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'store.example.test',
    ]);

    $resolver = app(TenantContextResolver::class);

    expect($resolver->resolve('store.example.test')?->is($tenant))->toBeTrue()
        ->and($resolver->resolve('dedicated-shop.'.config('tenancy.central_domain')))->toBeNull()
        ->and($resolver->resolve('other.example.test'))->toBeNull()
        ->and($resolver->isAllowedHost('other.example.test'))->toBeFalse();
});

it('allows Platform Admin access only in SaaS mode', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    $platform = app('filament')->getPanel('platform');

    expect($admin->canAccessPanel($platform))->toBeTrue();

    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect($admin->canAccessPanel($platform))->toBeFalse();
});

it('normalizes host case and trailing dots', function (): void {
    $resolver = app(TenantContextResolver::class);

    expect($resolver->normalizeHost(' SHOP.Example.TEST. '))->toBe('shop.example.test');
});
