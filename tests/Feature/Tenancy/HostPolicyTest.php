<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Models\Domain;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContextResolver;

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'deployment.allowed_hosts' => ['alias.example.test', '*.edge.example.test'],
        'deployment.dedicated.tenant_id' => null,
        'deployment.dedicated.canonical_host' => null,
    ]);
});

it('allows only central, tenant-subdomain, configured, and registered hosts', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'host-policy-shop']);
    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'custom.example.test',
    ]);

    $resolver = app(TenantContextResolver::class);
    $central = config('tenancy.central_domain');

    expect($resolver->isAllowedHost($central))->toBeTrue()
        ->and($resolver->isAllowedHost('host-policy-shop.'.$central))->toBeTrue()
        ->and($resolver->isAllowedHost('alias.example.test'))->toBeTrue()
        ->and($resolver->isAllowedHost('shop.edge.example.test'))->toBeTrue()
        ->and($resolver->isAllowedHost('custom.example.test'))->toBeTrue()
        ->and($resolver->isAllowedHost('unknown.example.test'))->toBeFalse();
});

it('does not trust arbitrary hosts in Dedicated mode', function (): void {
    $tenant = Tenant::factory()->create();

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'dedicated.example.test',
    ]);

    $patterns = app(TenantContextResolver::class)->trustedHostPatterns();

    expect($patterns)->toHaveCount(1)
        ->and($patterns[0])->toBe('^dedicated\\.example\\.test$');
});
