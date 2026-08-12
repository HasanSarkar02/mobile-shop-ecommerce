<?php

declare(strict_types=1);

use App\Exceptions\TenantContextRequiredException;
use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;

it('scopes attribute options to the active tenant', function (): void {
    $tenantA = actingAsTenant();
    $definitionA = AttributeDefinition::query()->create([
        'code' => 'size-a',
        'label' => 'Size',
        'data_type' => 'select',
    ]);

    $optionA = $definitionA->options()->create(['value' => 'M', 'label' => 'Medium']);
    expect($optionA->tenant_id)->toBe($tenantA->id);

    $tenantB = Tenant::factory()->create();
    app(Tenancy::class)->set($tenantB);

    $definitionB = AttributeDefinition::query()->create([
        'code' => 'size-b',
        'label' => 'Size',
        'data_type' => 'select',
    ]);

    $optionB = $definitionB->options()->create(['value' => 'M', 'label' => 'Medium']);
    expect($optionB->tenant_id)->toBe($tenantB->id);

    // Identical option values are allowed because they belong to different
    // tenants — the tenant-scoped unique constraint permits this.
    expect($optionB->tenant_id)->toBe($tenantB->id);
    expect($definitionB->options()->count())->toBe(1);
    expect(AttributeOption::query()->count())->toBe(1);

    // Tenant A's option remains isolated and is invisible from tenant B's context.
    app(Tenancy::class)->set($tenantA);
    expect(AttributeOption::query()->count())->toBe(1);
    expect($definitionA->options->first()->tenant_id)->toBe($tenantA->id);
    expect($definitionA->options->first()->id)->toBe($optionA->id);
});

it('fails closed when querying attribute options without a tenant context', function (): void {
    app(Tenancy::class)->set(null);

    AttributeOption::query()->count();
})->throws(TenantContextRequiredException::class);
