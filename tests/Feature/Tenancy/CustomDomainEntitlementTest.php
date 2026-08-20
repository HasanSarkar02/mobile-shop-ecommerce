<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\Tenancy\TenantContextResolver;
use Carbon\CarbonImmutable;

function createPhaseOnePlan(array $overrides = []): Plan
{
    return Plan::query()->create(array_merge([
        'name' => 'Phase One Plan '.fake()->unique()->numberBetween(1000, 9999),
        'slug' => 'phase-one-'.fake()->unique()->numberBetween(1000, 9999),
        'price' => 1000,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => true,
        'is_active' => true,
    ], $overrides));
}

function attachPhaseOneSubscription(Tenant $tenant, array $plan = [], array $subscription = []): void
{
    $planModel = createPhaseOnePlan($plan);

    TenantSubscription::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'plan_id' => $planModel->id,
        'status' => 'active',
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ], $subscription));
}

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'deployment.dedicated.tenant_id' => null,
        'deployment.dedicated.canonical_host' => null,
    ]);
});

it('allows a custom domain for an active entitled subscription', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    attachPhaseOneSubscription($tenant);
    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'entitled.example.test',
        'normalized_domain' => 'entitled.example.test',
        'status' => DomainStatus::Active,
        'verified_at' => now(),
    ]);

    expect(app(TenantContextResolver::class)->resolve('entitled.example.test')?->is($tenant))->toBeTrue();
});

it('denies custom domains when the plan or subscription is not eligible', function (): void {
    $cases = [
        ['subscription' => ['status' => 'past_due']],
        ['subscription' => ['status' => 'cancelled']],
        ['subscription' => ['status' => 'expired']],
        ['subscription' => ['current_period_ends_at' => CarbonImmutable::now()->subMinute()]],
        ['plan' => ['custom_domain_allowed' => false]],
        ['plan' => ['is_active' => false]],
    ];

    foreach ($cases as $index => $case) {
        $tenant = Tenant::factory()->create(['subdomain' => 'ineligible-'.$index]);
        attachPhaseOneSubscription($tenant, $case['plan'] ?? [], $case['subscription'] ?? []);
        Domain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'ineligible-'.$index.'.example.test',
        ]);

        expect(app(TenantContextResolver::class)->resolve('ineligible-'.$index.'.example.test'))->toBeNull();
    }
});

it('denies custom domains when the tenant has no subscription', function (): void {
    $tenant = Tenant::factory()->create();
    Domain::query()->create(['tenant_id' => $tenant->id, 'domain' => 'missing-subscription.example.test']);

    expect(app(TenantContextResolver::class)->resolve('missing-subscription.example.test'))->toBeNull();
});

it('keeps subdomain resolution independent of custom-domain entitlement', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'subdomain-without-plan']);
    attachPhaseOneSubscription($tenant, ['custom_domain_allowed' => false]);

    expect(app(TenantContextResolver::class)->resolve('subdomain-without-plan.'.config('tenancy.central_domain'))?->is($tenant))->toBeTrue();
});

it('does not apply SaaS subscription entitlement in Dedicated mode', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'dedicated-without-plan']);

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'dedicated-entitlement.example.test',
    ]);

    expect(app(TenantContextResolver::class)->resolve('dedicated-entitlement.example.test')?->is($tenant))->toBeTrue();
});
