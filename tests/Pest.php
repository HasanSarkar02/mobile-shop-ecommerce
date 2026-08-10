<?php

use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

function actingAsTenant(array $overrides = []): Tenant
{
    $tenant = Tenant::factory()->create($overrides);
    app(Tenancy::class)->set($tenant);

    return $tenant;
}

function createTestVariant(array $overrides = []): \App\Models\ProductVariant
{
    $product = \App\Models\Product::factory()->create(['status' => 'published']);
    \App\Models\ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    return \App\Models\ProductVariant::factory()->for($product)->create($overrides);
}

function createCartWithVariant(int $quantity = 1, ?\App\Models\Customer $customer = null): array
{
    $variant = createTestVariant();
    app(\App\Services\InventoryService::class)->restock($variant, 10);

    $cart = \App\Models\Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => $customer?->id,
        'currency_code' => 'BDT',
    ]);

    $cart->items()->create([
        'tenant_id' => $cart->tenant_id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'unit_price' => $variant->price,
    ]);

    return [$cart, $variant];
}