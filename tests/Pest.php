<?php

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\InventoryService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

function seedBootstrapPlans(): void
{
    Plan::query()->create([
        'name' => 'Free Trial', 'slug' => 'trial', 'price' => 0, 'billing_period' => 'monthly',
        'max_products' => 50, 'max_staff' => 2, 'custom_domain_allowed' => false, 'is_active' => true, 'sort_order' => 1,
    ]);
    Plan::query()->create([
        'name' => 'Starter', 'slug' => 'starter', 'price' => 99000, 'billing_period' => 'monthly',
        'max_products' => 500, 'max_staff' => 5, 'custom_domain_allowed' => true, 'is_active' => true, 'sort_order' => 2,
    ]);
    Plan::query()->create([
        'name' => 'Growth', 'slug' => 'growth', 'price' => 249000, 'billing_period' => 'monthly',
        'max_products' => null, 'max_staff' => null, 'custom_domain_allowed' => true, 'is_active' => true, 'sort_order' => 3,
    ]);
}

function actingAsTenant(array $overrides = []): Tenant
{
    $tenant = Tenant::factory()->create($overrides);
    app(Tenancy::class)->set($tenant);

    return $tenant;
}

function createTestVariant(array $overrides = []): ProductVariant
{
    $product = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    return ProductVariant::factory()->for($product)->create($overrides);
}

function createCartWithVariant(int $quantity = 1, ?Customer $customer = null): array
{
    $variant = createTestVariant();
    app(InventoryService::class)->restock($variant, 10);

    $cart = Cart::query()->create([
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
