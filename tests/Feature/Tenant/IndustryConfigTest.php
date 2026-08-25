<?php

declare(strict_types=1);

use App\Enums\TenantIndustry;
use App\Models\Tenant;
use App\Support\IndustryConfig;
use App\Support\Tenancy\Tenancy;

it('resolves tenant industry to its preset', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Fashion->value]);
    expect($tenant->industry)->toBe(TenantIndustry::Fashion);
    expect($tenant->industryCode())->toBe('fashion');

    $preset = IndustryConfig::current();
    expect($preset['pdp']['layout'])->toBe('imagery-led');
    expect($preset['label'])->toBe('Fashion');
});

it('dynamically changes config when tenant industry changes', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Grocery->value]);
    expect(IndustryConfig::current()['theme']['preset'])->toBe('grocery');
    expect(IndustryConfig::current()['pdp']['layout'])->toBe('standard');

    $tenant->update(['industry' => TenantIndustry::Electronics->value]);
    // Refresh Tenancy singleton still points to same model instance; reload to reflect
    $fresh = $tenant->fresh();
    app(Tenancy::class)->set($fresh);

    expect(IndustryConfig::current()['theme']['preset'])->toBe('electronics');
    expect(IndustryConfig::current()['pdp']['layout'])->toBe('spec-led');
});

it('falls back to general for unknown or missing industry', function (): void {
    $tenant = actingAsTenant(['industry' => 'general']);
    expect(IndustryConfig::get('unknown_vertical', 'pdp.layout'))->toBe('standard');
    expect(IndustryConfig::get('general', 'pdp.layout'))->toBe('standard');

    // Tenant with null industry (pre-migration) falls back to general
    $tenant2 = Tenant::factory()->make(['industry' => null]);
    app(Tenancy::class)->set($tenant2);
    expect(IndustryConfig::current()['label'])->toBe('General');
    expect(IndustryConfig::currentGet('pdp.layout'))->toBe('standard');
});

it('falls back to general for missing config keys', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Sports->value]);
    // Sports does not define pdp.layout, should inherit from general
    expect(IndustryConfig::current()['pdp']['layout'])->toBe('standard');
    expect(IndustryConfig::currentGet('facets.priority'))->toBe([]);
    // Completely missing key should return default
    expect(IndustryConfig::get('fashion', 'nonexistent.key', 'fallback'))->toBe('fallback');
    expect(IndustryConfig::currentGet('nonexistent.key', 'fb'))->toBe('fb');
});

it('defaults new tenants to general and persists industry via signup', function (): void {
    $tenant = actingAsTenant();
    expect($tenant->fresh()->industry)->toBe(TenantIndustry::General);
    expect($tenant->fresh()->industryCode())->toBe('general');
});

it('validates industry via TenantIndustry enum options', function (): void {
    expect(TenantIndustry::options())->toHaveKeys(['general', 'electronics', 'mobile', 'fashion', 'grocery', 'sports', 'furniture']);
    expect(TenantIndustry::tryFrom('fashion'))->toBe(TenantIndustry::Fashion);
    expect(TenantIndustry::tryFrom('invalid'))->toBeNull();
});
