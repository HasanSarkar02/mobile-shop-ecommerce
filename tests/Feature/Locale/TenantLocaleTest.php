<?php

declare(strict_types=1);

use App\Models\Tenant;

it('defaults to en when locales is null or empty', function (): void {
    $tenant = Tenant::factory()->make(['locales' => null, 'preferred_locale' => 'en']);
    expect($tenant->enabledLocales())->toBe(['en']);
    expect($tenant->supportsLocale('en'))->toBeTrue();
    expect($tenant->supportsLocale('bn'))->toBeFalse();

    $tenant2 = Tenant::factory()->make(['locales' => [], 'preferred_locale' => 'en']);
    expect($tenant2->enabledLocales())->toBe(['en']);
});

it('supports bn when explicitly enabled', function (): void {
    $tenant = Tenant::factory()->make(['locales' => ['en', 'bn'], 'preferred_locale' => 'en']);
    expect($tenant->supportsLocale('bn'))->toBeTrue();
    expect($tenant->supportsLocale('BN'))->toBeTrue();
    expect($tenant->enabledLocales())->toBe(['en', 'bn']);
});

it('always ensures en is present even if only bn was stored', function (): void {
    $tenant = Tenant::factory()->make(['locales' => ['bn'], 'preferred_locale' => 'bn']);
    expect($tenant->enabledLocales())->toBe(['en', 'bn']);
    expect($tenant->supportsLocale('en'))->toBeTrue();
});

it('preferred locale falls back to en when unsupported', function (): void {
    $tenant = Tenant::factory()->make(['locales' => ['en'], 'preferred_locale' => 'bn']);
    expect($tenant->preferredLocale())->toBe('en');

    $tenant2 = Tenant::factory()->make(['locales' => ['en', 'bn'], 'preferred_locale' => 'bn']);
    expect($tenant2->preferredLocale())->toBe('bn');

    $tenant3 = Tenant::factory()->make(['locales' => ['en', 'bn'], 'preferred_locale' => 'EN']);
    expect($tenant3->preferredLocale())->toBe('en');
});

it('normalizes locales to lower-case and unique', function (): void {
    $tenant = Tenant::factory()->make(['locales' => ['EN', 'bn', 'BN', 'en'], 'preferred_locale' => 'en']);
    expect($tenant->enabledLocales())->toBe(['en', 'bn']);
});

it('persists locales and preferred_locale via database', function (): void {
    $tenant = actingAsTenant(['locales' => ['en', 'bn'], 'preferred_locale' => 'bn']);

    $fresh = Tenant::query()->findOrFail($tenant->id);
    expect($fresh->enabledLocales())->toBe(['en', 'bn']);
    expect($fresh->preferredLocale())->toBe('bn');
    expect($fresh->supportsLocale('bn'))->toBeTrue();
});

it('existing tenants are backfilled to en after migration', function (): void {
    // Simulate a tenant created before the migration (raw insert without locales)
    $tenant = Tenant::factory()->make(['locales' => null]);
    expect($tenant->enabledLocales())->toBe(['en']);
    expect($tenant->preferredLocale())->toBe('en');
});
