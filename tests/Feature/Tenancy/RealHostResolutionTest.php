<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\Tenancy\Tenancy;

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'deployment.dedicated.tenant_id' => null,
        'deployment.dedicated.canonical_host' => null,
    ]);

    app(Tenancy::class)->set(null);
});

it('resolves a real SaaS subdomain request and fails closed for an unknown host', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'real-host-shop']);
    $plan = Plan::query()->create([
        'name' => 'Real Host SaaS Plan',
        'slug' => 'real-host-saas-plan',
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
    $central = config('tenancy.central_domain');

    $this->get('http://'.$central.'/up')->assertOk();
    expect(app(Tenancy::class)->get())->toBeNull();

    $this->get('http://real-host-shop.'.$central.'/up')->assertOk();
    expect(app(Tenancy::class)->get()?->is($tenant))->toBeTrue();

    $this->get('http://unknown-host.example.test/up')->assertNotFound();
    expect(app(Tenancy::class)->get())->toBeNull();
});

it('resolves a real Dedicated request and fails closed for the wrong host', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'real-dedicated-shop']);

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'real-dedicated.example.test',
    ]);

    $this->get('http://real-dedicated.example.test/up')->assertOk();
    expect(app(Tenancy::class)->get()?->is($tenant))->toBeTrue();

    app(Tenancy::class)->set(null);
    $this->get('http://wrong-dedicated.example.test/up')->assertNotFound();
    expect(app(Tenancy::class)->get())->toBeNull();
});

it('resolves an entitled custom domain through the real middleware', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'real-custom-shop']);
    $plan = Plan::query()->create([
        'name' => 'Custom Domain Test Plan',
        'slug' => 'custom-domain-test-plan',
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
    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'real-custom.example.test',
        'normalized_domain' => 'real-custom.example.test',
        'status' => DomainStatus::Active,
        'verified_at' => now(),
    ]);

    $this->get('http://real-custom.example.test/up')->assertOk();
    expect(app(Tenancy::class)->get()?->is($tenant))->toBeTrue();
});

it('rejects a non-entitled custom domain through the real middleware', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'real-non-entitled-shop']);
    $plan = Plan::query()->create([
        'name' => 'No Custom Domain Test Plan',
        'slug' => 'no-custom-domain-test-plan',
        'price' => 1000,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => false,
        'is_active' => true,
    ]);
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'real-non-entitled.example.test',
        'normalized_domain' => 'real-non-entitled.example.test',
        'status' => DomainStatus::Active,
        'verified_at' => now(),
    ]);

    $this->get('http://real-non-entitled.example.test/up')->assertNotFound();
    expect(app(Tenancy::class)->get())->toBeNull();
});

it('does not expose the Platform panel on a Dedicated host', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'platform-blocked-shop']);

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'platform-blocked.example.test',
    ]);

    $this->get('http://'.config('tenancy.central_domain').'/platform')->assertNotFound();
});
