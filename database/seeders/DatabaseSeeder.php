<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\ShippingMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SubscriptionService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $trialPlan = Plan::query()->create([
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
        $tenant = Tenant::query()->create([
            'name' => 'Demo Store',
            'subdomain' => 'demo',
            'status' => 'active',
        ]);

        // Everything below reads/writes tenant-scoped models (e.g. InventoryService's
        // restock() looks up the tenant's default Location), so a tenant context must
        // be resolved for the duration of this seeding block, same as any other
        // non-HTTP context (console command, job).
        app(Tenancy::class)->set($tenant);

        app(SubscriptionService::class)->startTrial($tenant, $trialPlan, 14);

        $adminPassword = bin2hex(random_bytes(16));
        $admin = User::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@hasanmobileshop.com',
            'password' => bcrypt($adminPassword),
        ]);
        $admin->forceFill(['is_platform_admin' => true, 'is_active' => true])->save();

        if ($this->command) {
            $this->command->info('Platform Admin credentials: admin@hasanmobileshop.com / '.$adminPassword);
        }

        $owner = User::query()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@demo.test',
            'password' => bcrypt('password'),
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true])->save();
        $tenant->forceFill(['primary_owner_id' => $owner->id])->save();

        $brand = Brand::query()->create(['name' => 'Samsung', 'tenant_id' => $tenant->id]);
        $category = Category::query()->create(['name' => 'Smartphones', 'tenant_id' => $tenant->id]);

        $product = Product::query()->create([
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

        Customer::query()->create([
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

        app(InventoryService::class)->restock($variant1, 25, null, 'Initial demo stock');

        SerialNumber::query()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant1->id,
            'imei_or_serial' => '359123456789012',
            'status' => 'available',
        ]);

        PaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cash on Delivery',
            'type' => 'cod',
            'is_active' => true,
        ]);

        PaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Online Payment (bKash/Nagad/Card)',
            'type' => 'aggregator',
            'gateway_driver' => 'sslcommerz',
            'is_active' => true,
        ]);

        ShippingMethod::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard Delivery',
            'type' => 'flat_rate',
            'cost' => 6000,
            'is_active' => true,
        ]);

        $this->call(CourierProviderSeeder::class);

        app(Tenancy::class)->set(null);
    }
}
