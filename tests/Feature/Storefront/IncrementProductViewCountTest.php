<?php

declare(strict_types=1);

use App\Jobs\IncrementProductViewCount;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;

it('increments view_count without ambient tenant context (queue-worker safe)', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'status' => 'published']);

    // A queue worker has no tenant context resolved; the job must set its own.
    app(Tenancy::class)->set(null);

    (new IncrementProductViewCount($product->id, $tenant->id))->handle();

    expect($product->fresh()->view_count)->toBe(1);
});

it('restores the prior tenant context after handling', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $other = Tenant::factory()->create(['status' => 'active']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'status' => 'published']);

    app(Tenancy::class)->set($other);

    (new IncrementProductViewCount($product->id, $tenant->id))->handle();

    expect(app(Tenancy::class)->get()?->id)->toBe($other->id);
});

it('no-ops without error when the tenant no longer exists', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    app(Tenancy::class)->set($tenant);

    $product = Product::factory()->create(['status' => 'published']);

    app(Tenancy::class)->set(null);
    $missingTenantId = 999999;

    (new IncrementProductViewCount($product->id, $missingTenantId))->handle();

    expect($product->fresh()->view_count)->toBe(0);
});
