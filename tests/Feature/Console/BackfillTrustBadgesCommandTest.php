<?php

declare(strict_types=1);

use App\Models\HomepageSection;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates missing trust_badges section', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'backfill-create']);
    app(Tenancy::class)->set($tenant);
    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();

    $before = HomepageSection::query()->where('tenant_id', $tenant->id)->where('type', 'trust_badges')->count();
    expect($before)->toBe(0);

    $this->artisan('tenants:backfill-trust-badges')->assertExitCode(0);

    app(Tenancy::class)->set($tenant);
    expect(HomepageSection::query()->where('tenant_id', $tenant->id)->where('type', 'trust_badges')->count())->toBe(1);
    expect(HomepageSection::query()->where('tenant_id', $tenant->id)->where('type', 'trust_badges')->first()->config)->toBe([]);
});

it('does not duplicate existing trust_badges', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'backfill-nodup']);
    app(Tenancy::class)->set($tenant);
    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => ['items' => [['icon' => 'shield', 'label' => 'Keep Me', 'sub' => 'keep']]],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $this->artisan('tenants:backfill-trust-badges')->assertExitCode(0);
    $this->artisan('tenants:backfill-trust-badges')->assertExitCode(0);

    app(Tenancy::class)->set($tenant);
    expect(HomepageSection::query()->where('tenant_id', $tenant->id)->where('type', 'trust_badges')->count())->toBe(1);
});

it('preserves existing config.items', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'backfill-preserve']);
    app(Tenancy::class)->set($tenant);
    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    $custom = ['items' => [['icon' => 'shield', 'label' => 'Preserve Me', 'sub' => 'sub']]];
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'trust_badges',
        'title' => 'Our Promise',
        'config' => $custom,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $this->artisan('tenants:backfill-trust-badges')->assertExitCode(0);

    app(Tenancy::class)->set($tenant);
    $after = HomepageSection::query()->where('tenant_id', $tenant->id)->where('type', 'trust_badges')->first();
    expect($after->config['items'][0]['label'])->toBe('Preserve Me')
        ->and($after->config['items'][0]['icon'])->toBe('shield');
});

it('is idempotent across multiple runs and tenants', function (): void {
    $t1 = actingAsTenant(['subdomain' => 'backfill-idem1']);
    app(Tenancy::class)->set($t1);
    HomepageSection::query()->where('tenant_id', $t1->id)->delete();

    $t2 = Tenant::factory()->create(['subdomain' => 'backfill-idem2']);
    $trialPlan = Plan::query()->where('slug', 'trial')->firstOrFail();
    app(SubscriptionService::class)->startTrial($t2, $trialPlan, 14);
    // t2 starts with no trust_badges

    $this->artisan('tenants:backfill-trust-badges')->assertExitCode(0);
    $this->artisan('tenants:backfill-trust-badges')->assertExitCode(0);

    foreach ([$t1, $t2] as $t) {
        app(Tenancy::class)->set($t);
        expect(HomepageSection::query()->where('tenant_id', $t->id)->where('type', 'trust_badges')->count())->toBe(1);
    }
});
