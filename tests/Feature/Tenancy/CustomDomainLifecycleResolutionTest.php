<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Str;

function createResolutionTenant(array $plan = [], ?array $subscription = []): Tenant
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'resolution-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);

    if ($subscription !== null) {
        $planModel = Plan::query()->create(array_merge([
            'name' => 'Resolution Plan '.Str::random(8),
            'slug' => 'resolution-'.Str::lower(Str::random(8)),
            'price' => 1000,
            'billing_period' => 'monthly',
            'custom_domain_allowed' => true,
            'is_active' => true,
        ], $plan));
        TenantSubscription::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'plan_id' => $planModel->id,
            'status' => 'active',
            'current_period_starts_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ], $subscription));
    }

    return $tenant;
}

function createResolutionDomain(Tenant $tenant, string $host, DomainStatus $status, bool $verified): Domain
{
    return Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => $host,
        'normalized_domain' => $host,
        'status' => $status,
        'verified_at' => $verified ? now() : null,
    ]);
}

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'deployment.dedicated.tenant_id' => null,
        'deployment.dedicated.canonical_host' => null,
    ]);
    app(Tenancy::class)->set(null);
});

it('routes only active verified entitled custom domains', function (): void {
    $activeTenant = createResolutionTenant();
    createResolutionDomain($activeTenant, 'active-verified.example.test', DomainStatus::Active, true);

    $this->get('http://active-verified.example.test/up')->assertOk();
    expect(app(Tenancy::class)->get()?->is($activeTenant))->toBeTrue();

    foreach ([
        [DomainStatus::Pending, false],
        [DomainStatus::Failed, false],
        [DomainStatus::Verified, true],
        [DomainStatus::Active, false],
        [DomainStatus::Suspended, true],
        [DomainStatus::Revoked, true],
    ] as $index => [$status, $verified]) {
        app(Tenancy::class)->set(null);
        $tenant = createResolutionTenant();
        $host = 'blocked-'.$index.'-'.Str::lower(Str::random(5)).'.example.test';
        createResolutionDomain($tenant, $host, $status, $verified);

        $this->get('http://'.$host.'/up')->assertNotFound();
        expect(app(Tenancy::class)->get())->toBeNull();
    }
});

it('fails closed for every invalid subscription entitlement', function (): void {
    $cases = [
        ['plan' => ['custom_domain_allowed' => false]],
        ['plan' => ['is_active' => false]],
        ['subscription' => ['status' => 'past_due']],
        ['subscription' => ['status' => 'cancelled']],
        ['subscription' => ['status' => 'expired']],
        ['subscription' => ['current_period_ends_at' => now()->subMinute()]],
        ['subscription' => null],
    ];

    foreach ($cases as $index => $case) {
        $subscription = array_key_exists('subscription', $case) ? $case['subscription'] : [];
        $tenant = createResolutionTenant($case['plan'] ?? [], $subscription);
        $host = 'ineligible-'.$index.'-'.Str::lower(Str::random(5)).'.example.test';
        createResolutionDomain($tenant, $host, DomainStatus::Active, true);

        $this->get('http://'.$host.'/up')->assertNotFound();
        expect(app(Tenancy::class)->get())->toBeNull();
    }
});

it('does not let a Dedicated deployment select SaaS hosts or domain records', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    createResolutionDomain($tenant, 'saas-record.example.test', DomainStatus::Active, true);

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'dedicated-resolution.example.test',
    ]);

    $this->get('http://dedicated-resolution.example.test/up')->assertOk();
    expect(app(Tenancy::class)->get()?->is($tenant))->toBeTrue();

    app(Tenancy::class)->set(null);
    $this->get('http://saas-record.example.test/up')->assertNotFound();
    $this->get('http://arbitrary.'.config('tenancy.central_domain').'/up')->assertNotFound();
    expect(app(Tenancy::class)->get())->toBeNull();
});

it('preserves valid tenant subdomain resolution regardless of custom-domain status', function (): void {
    $tenant = createResolutionTenant();
    $tenant->update(['subdomain' => 'subdomain-resolution']);

    $this->get('http://subdomain-resolution.'.config('tenancy.central_domain').'/up')->assertOk();
    expect(app(Tenancy::class)->get()?->is($tenant))->toBeTrue();
});
