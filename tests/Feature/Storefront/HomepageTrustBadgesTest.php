<?php

declare(strict_types=1);

use App\Enums\TenantIndustry;
use App\Models\HomepageSection;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function trustTenant(string $subdomain, string $industry = 'general'): Tenant
{
    return actingAsTenant(['subdomain' => $subdomain, 'industry' => $industry]);
}

function getHome(string $subdomain): string
{
    return get('http://'.$subdomain.'.'.config('tenancy.central_domain').'/')->assertOk()->getContent();
}

it('renders industry preset when no tenant override', function (): void {
    $tenant = trustTenant('trust-preset-grocery', TenantIndustry::Grocery->value);
    app(Tenancy::class)->set($tenant);

    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => [],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $html = getHome('trust-preset-grocery');

    expect($html)->toContain('Fresh')
        ->and($html)->toContain('Same-Day Delivery');
});

it('renders tenant override when config.items non-empty', function (): void {
    $tenant = trustTenant('trust-override', TenantIndustry::Grocery->value);
    app(Tenancy::class)->set($tenant);

    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => ['items' => [
            ['icon' => 'shield', 'label' => 'Custom One', 'sub' => 'Custom sub 1'],
            ['icon' => 'truck', 'label' => 'Custom Two', 'sub' => 'Custom sub 2'],
        ]],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $html = getHome('trust-override');

    expect($html)->toContain('Custom One')
        ->and($html)->toContain('Custom Two')
        ->and($html)->not->toContain('Fresh &amp; Quality');
});

it('falls back to preset when config.items is empty array', function (): void {
    $tenant = trustTenant('trust-empty', TenantIndustry::Furniture->value);
    app(Tenancy::class)->set($tenant);

    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => ['items' => []],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $html = getHome('trust-empty');

    expect($html)->toContain('Quality Assured')
        ->and($html)->toContain('Installation Available');
});

it('falls back to preset when config.items missing or null', function (): void {
    $tenant = trustTenant('trust-null', TenantIndustry::Electronics->value);
    app(Tenancy::class)->set($tenant);

    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => [],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $html = getHome('trust-null');

    expect($html)->toContain('Genuine Products')
        ->and($html)->toContain('Manufacturer Warranty');
});

it('tenant A override does not leak to tenant B', function (): void {
    $tenantA = trustTenant('trust-a', TenantIndustry::Grocery->value);
    app(Tenancy::class)->set($tenantA);
    HomepageSection::query()->where('tenant_id', $tenantA->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenantA->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => ['items' => [['icon' => 'shield', 'label' => 'A Only', 'sub' => 'A sub']]],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $tenantB = Tenant::factory()->create(['subdomain' => 'trust-b', 'industry' => TenantIndustry::Grocery->value]);
    $trialPlan = Plan::query()->where('slug', 'trial')->firstOrFail();
    app(SubscriptionService::class)->startTrial($tenantB, $trialPlan, 14);
    app(Tenancy::class)->set($tenantB);
    HomepageSection::query()->where('tenant_id', $tenantB->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenantB->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => [],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $htmlB = getHome('trust-b');

    expect($htmlB)->not->toContain('A Only')
        ->and($htmlB)->toContain('Fresh');

    $htmlA = getHome('trust-a');
    expect($htmlA)->toContain('A Only');
});

it('respects is_active and visibility', function (): void {
    $tenant = trustTenant('trust-inactive', TenantIndustry::General->value);
    app(Tenancy::class)->set($tenant);

    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => ['items' => [['icon' => 'shield', 'label' => 'Should Not Show', 'sub' => 'hidden']]],
        'is_active' => false,
        'sort_order' => 2,
    ]);

    $html = getHome('trust-inactive');

    expect($html)->not->toContain('Should Not Show');
});
