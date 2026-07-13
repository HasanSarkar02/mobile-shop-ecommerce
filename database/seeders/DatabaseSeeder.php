<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Demo Store',
            'subdomain' => 'demo',
            'status' => 'active',
        ]);

        User::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@hasanmobileshop.com',
            'password' => bcrypt('password'),
            'is_platform_admin' => true,
        ]);

        User::query()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@demo.test',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $brand = \App\Models\Brand::query()->create(['name' => 'Samsung', 'tenant_id' => $tenant->id]);
        $category = \App\Models\Category::query()->create(['name' => 'Smartphones', 'tenant_id' => $tenant->id]);

        $product = \App\Models\Product::query()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'model_number' => 'GAL-DEMO',

            'status' => 'published',
        ]);

        $product->translations()->create([
            'tenant_id' => $tenant->id,
            'locale' => 'en',
            'name' => 'Galaxy Demo Phone',
            'slug' => 'galaxy-demo-phone',
        ]);

        \App\Models\Customer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Customer',
            'email' => 'customer@demo.test',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
        ]);

        $variant1 = $product->variants()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'GAL-DEMO-128-8',
            'price' => 3500000,
            'inventory_type' => 'tracked',
            'fulfillment_strategy' => 'stock',
            'availability' => 'in_stock',
        ]);

        $product->variants()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'GAL-DEMO-512-12',
            'price' => 4500000,
            'inventory_type' => 'not_tracked',
            'fulfillment_strategy' => 'preorder',
            'availability' => 'in_stock',
            'expected_available_at' => now()->addDays(21),
        ]);

        app(\App\Services\InventoryService::class)->restock($variant1, 25, null, 'Initial demo stock');

        \App\Models\SerialNumber::query()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant1->id,
            'imei_or_serial' => '359123456789012',
            'status' => 'available',
        ]);
    }
}