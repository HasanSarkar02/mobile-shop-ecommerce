<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\AttributeDataType;
use App\Enums\CampaignStatus;
use App\Enums\LinkType;
use App\Enums\MenuLocation;
use App\Enums\PaymentMethodType;
use App\Enums\ProductStatus;
use App\Enums\ShippingMethodType;
use App\Enums\Visibility;
use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\StockItem;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Support\ThemePresets;
use Database\Seeders\TrustContentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ElectronicsIndustrySeeder
{
    public function seed(Tenant $tenant): void
    {
        $this->seedCategories($tenant);
        $this->seedAttributes($tenant);
        $this->seedBrands($tenant);
        $this->seedCampaigns($tenant);
        $this->seedBanners($tenant);
        $this->seedHomepage($tenant);
        $this->seedProducts($tenant);
        $this->seedMenus($tenant);
        $this->seedSupportDefaults($tenant);
        $this->seedTheme($tenant);
    }

    private function seedTheme(Tenant $tenant): void
    {
        $preset = ThemePresets::forIndustry('electronics');

        $existing = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->first();

        if ($existing === null) {
            StoreThemeSetting::query()->create([
                'tenant_id' => $tenant->id,
                'primary_color' => $preset['primary'],
                'secondary_color' => $preset['secondary'],
            ]);

            return;
        }

        $updates = [];

        $primaryRaw = $existing->getAttribute('primary_color');
        if ($primaryRaw === null || $primaryRaw === '') {
            $updates['primary_color'] = $preset['primary'];
        }

        $secondaryRaw = $existing->getAttribute('secondary_color');
        if ($secondaryRaw === null || $secondaryRaw === '') {
            $updates['secondary_color'] = $preset['secondary'];
        }

        if ($updates !== []) {
            $existing->update($updates);
        }
    }

    private function seedCategories(Tenant $tenant): void
    {
        // Keep original 3 for backward compat (tests expect them)
        $smartphones = $this->firstOrCreateCategory($tenant, 'Smartphones', null, 'smartphones');
        $this->firstOrCreateCategory($tenant, 'Accessories', $smartphones->id, 'accessories');

        // Legacy Laptops - keep
        $laptopsLegacy = $this->firstOrCreateCategory($tenant, 'Laptops', null, 'laptop-accessories');

        // ── Expand to full recommended taxonomy ─────────────────────────────
        // 1. Smartphones already exists - add its 6 sub-categories
        $this->firstOrCreateCategory($tenant, 'Android Phones', $smartphones->id, 'android-phones');
        $this->firstOrCreateCategory($tenant, 'iPhone', $smartphones->id, 'iphone');
        $this->firstOrCreateCategory($tenant, 'Gaming Phones', $smartphones->id, 'gaming-phones');
        $this->firstOrCreateCategory($tenant, 'Foldable Phones', $smartphones->id, 'foldable-phones');
        $this->firstOrCreateCategory($tenant, 'Budget Phones', $smartphones->id, 'budget-phones');
        $this->firstOrCreateCategory($tenant, 'Flagship Phones', $smartphones->id, 'flagship-phones');

        // 2. Feature Phones
        $featurePhones = $this->firstOrCreateCategory($tenant, 'Feature Phones', null, 'feature-phones');
        $this->firstOrCreateCategory($tenant, 'Button Phones', $featurePhones->id, 'feature-phones');
        $this->firstOrCreateCategory($tenant, 'Basic Phones', $featurePhones->id, 'feature-phones');

        // 3. Tablets
        $tablets = $this->firstOrCreateCategory($tenant, 'Tablets', null, 'tablets');
        $this->firstOrCreateCategory($tenant, 'Android Tablets', $tablets->id, 'tablets');
        $this->firstOrCreateCategory($tenant, 'iPad', $tablets->id, 'tablets');
        $this->firstOrCreateCategory($tenant, 'Kids Tablets', $tablets->id, 'tablets');

        // 4. Mobile Accessories (distinct from legacy Accessories child)
        $mobileAcc = $this->firstOrCreateCategory($tenant, 'Mobile Accessories', null, 'mobile-accessories');
        $this->firstOrCreateCategory($tenant, 'Chargers & Adapters', $mobileAcc->id, 'chargers');
        $this->firstOrCreateCategory($tenant, 'USB Cables', $mobileAcc->id, 'cables');
        $this->firstOrCreateCategory($tenant, 'Power Banks', $mobileAcc->id, 'power-banks');
        $this->firstOrCreateCategory($tenant, 'TWS & Earphones', $mobileAcc->id, 'tws-earphones');
        $this->firstOrCreateCategory($tenant, 'Headphones', $mobileAcc->id, 'headphones');
        $this->firstOrCreateCategory($tenant, 'Cases & Covers', $mobileAcc->id, 'cases-covers');
        $this->firstOrCreateCategory($tenant, 'Screen Protectors', $mobileAcc->id, 'screen-protectors');
        $this->firstOrCreateCategory($tenant, 'Car Holders', $mobileAcc->id, 'mobile-accessories');
        $this->firstOrCreateCategory($tenant, 'Mobile Stands', $mobileAcc->id, 'mobile-accessories');
        $this->firstOrCreateCategory($tenant, 'OTG & Converters', $mobileAcc->id, 'cables');

        // 5. Smart Watches
        $watches = $this->firstOrCreateCategory($tenant, 'Smart Watches', null, 'smart-watches');
        $this->firstOrCreateCategory($tenant, 'Smart Watch', $watches->id, 'smart-watches');
        $this->firstOrCreateCategory($tenant, 'Fitness Band', $watches->id, 'smart-watches-cat');
        $this->firstOrCreateCategory($tenant, 'Watch Accessories', $watches->id, 'smart-watches');

        // 6. Audio
        $audio = $this->firstOrCreateCategory($tenant, 'Audio', null, 'audio');
        $this->firstOrCreateCategory($tenant, 'TWS', $audio->id, 'tws-earphones');
        $this->firstOrCreateCategory($tenant, 'Bluetooth Speakers', $audio->id, 'audio');
        $this->firstOrCreateCategory($tenant, 'Neckbands', $audio->id, 'audio');
        $this->firstOrCreateCategory($tenant, 'Wired Earphones', $audio->id, 'tws-earphones');
        // Headphones under Audio needs distinct slug to avoid unique violation with Headphones under Mobile Accessories
        $this->firstOrCreateCategory($tenant, 'Audio Headphones', $audio->id, 'headphones');

        // 7. Power & Charging
        $power = $this->firstOrCreateCategory($tenant, 'Power & Charging', null, 'power-charging');
        $this->firstOrCreateCategory($tenant, 'Fast Chargers', $power->id, 'chargers');
        $this->firstOrCreateCategory($tenant, 'Wireless Chargers', $power->id, 'chargers');
        $this->firstOrCreateCategory($tenant, 'Charging Power Banks', $power->id, 'power-banks');
        $this->firstOrCreateCategory($tenant, 'Charging Stations', $power->id, 'power-charging');

        // 8. Networking
        $net = $this->firstOrCreateCategory($tenant, 'Networking', null, 'networking');
        $this->firstOrCreateCategory($tenant, 'Wi-Fi Routers', $net->id, 'networking');
        $this->firstOrCreateCategory($tenant, '4G/5G Routers', $net->id, 'networking');
        $this->firstOrCreateCategory($tenant, 'MiFi', $net->id, 'networking');
        $this->firstOrCreateCategory($tenant, 'Wi-Fi Extenders', $net->id, 'networking');

        // 9. Gadgets
        $gadgets = $this->firstOrCreateCategory($tenant, 'Gadgets', null, 'gadgets');
        $this->firstOrCreateCategory($tenant, 'Smart Gadgets', $gadgets->id, 'gadgets');
        $this->firstOrCreateCategory($tenant, 'Gaming Accessories', $gadgets->id, 'gadgets');
        $this->firstOrCreateCategory($tenant, 'Bluetooth Gadgets', $gadgets->id, 'gadgets');
        $this->firstOrCreateCategory($tenant, 'USB Gadgets', $gadgets->id, 'gadgets');

        // 10. Laptop & Computer Accessories
        $laptopAcc = $this->firstOrCreateCategory($tenant, 'Laptop & Computer Accessories', null, 'laptop-accessories');
        $this->firstOrCreateCategory($tenant, 'Mouse', $laptopAcc->id, 'laptop-accessories');
        $this->firstOrCreateCategory($tenant, 'Keyboard', $laptopAcc->id, 'laptop-accessories');
        $this->firstOrCreateCategory($tenant, 'Webcam', $laptopAcc->id, 'laptop-accessories');
        $this->firstOrCreateCategory($tenant, 'USB Hub', $laptopAcc->id, 'laptop-accessories');
        $this->firstOrCreateCategory($tenant, 'Laptop Stand', $laptopAcc->id, 'laptop-accessories');
        $this->firstOrCreateCategory($tenant, 'Laptop Bags', $laptopAcc->id, 'laptop-accessories');

        // Ensure legacy Laptops still has image
        $this->ensureCategoryImage($laptopsLegacy, 'electronics/categories/laptop-accessories');
    }

    private function seedBrands(Tenant $tenant): void
    {
        $brands = ['Apple', 'Samsung', 'Xiaomi', 'OnePlus', 'Vivo', 'OPPO', 'Realme', 'Google', 'Nothing', 'Nokia'];
        foreach ($brands as $name) {
            $brand = $this->firstOrCreateBrand($tenant, $name);
            // map to starter file stem (lowercase)
            $stemMap = [
                'Apple' => 'electronics/brands/apple',
                'Samsung' => 'electronics/brands/samsung',
                'Xiaomi' => 'electronics/brands/xiaomi',
                'OnePlus' => 'electronics/brands/oneplus',
                'Vivo' => 'electronics/brands/vivo',
                'OPPO' => 'electronics/brands/oppo',
                'Realme' => 'electronics/brands/realme',
                'Google' => 'electronics/brands/google',
                'Nothing' => 'electronics/brands/nothing',
                'Nokia' => 'electronics/brands/nothing', // fallback placeholder
            ];
            $this->ensureBrandLogo($brand, $stemMap[$name] ?? 'electronics/brands/apple');
        }
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, [
            'code' => 'storage_capacity',
            'label' => 'Storage Capacity',
            'data_type' => AttributeDataType::Select,
            'unit' => 'GB',
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 1,
        ], ['64GB', '128GB', '256GB', '512GB']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'ram',
            'label' => 'RAM',
            'data_type' => AttributeDataType::Select,
            'unit' => 'GB',
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 2,
        ], ['4GB', '8GB', '12GB', '16GB']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'device_color',
            'label' => 'Device Color',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Appearance',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 3,
        ], ['Black', 'White', 'Blue', 'Titanium', 'Silver', 'Graphite', 'Green', 'Pink']);
    }

    private function seedCampaigns(Tenant $tenant): void
    {
        $campaign = Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'hot-deals-flash-sale'],
            [
                'name' => 'Hot Deals - Flash Sale',
                'description' => 'Limited time flash sale on bestselling mobiles and accessories. Up to 30% OFF.',
                'accent_color' => '#ef4444',
                'short_tagline' => 'Up to 30% OFF - Ends Soon!',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(5),
            ]
        );

        // Also secondary promo campaign for promotional banner linking
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'new-arrivals-promo'],
            [
                'name' => 'New Arrivals',
                'description' => 'Discover the latest arrivals - iPhone 16, Galaxy S25 and more.',
                'accent_color' => '#2563eb',
                'short_tagline' => 'Just Landed',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->addDays(20),
            ]
        );
    }

    private function seedHomepage(Tenant $tenant): void
    {
        // Keep original 4 for test compat, then expand.
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Hero Banner', 10, ['placement' => 'hero']);
        $this->firstOrCreateHomepageSection($tenant, 'trust_badges', 'Our Promise', 20);
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Category', 30, ['source' => 'category', 'limit' => 10]);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Featured Electronics', 40, ['data_source' => 'featured', 'limit' => 8]);

        // Additional requested sections
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Brand', 50, ['source' => 'brand', 'limit' => 9]);

        // Hot Deals / Flash Sale - campaign sourced
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'hot-deals-flash-sale')->first();
        $hotDeals = $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Hot Deals - Flash Sale', 60, ['data_source' => 'campaign', 'limit' => 8]);
        if ($campaign && $hotDeals->campaign_id !== $campaign->id) {
            $hotDeals->update(['campaign_id' => $campaign->id]);
        }

        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'New Arrivals', 70, ['data_source' => 'latest', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Best Selling Products', 80, ['data_source' => 'best_selling', 'limit' => 8]);

        // Promotional Banner - second banner carousel with promo placement
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Promotional Banner', 90, ['placement' => 'promo']);

        $this->firstOrCreateHomepageSection($tenant, 'blog_grid', 'From the Journal', 100, ['limit' => 3]);

        $this->firstOrCreateHomepageSection($tenant, 'newsletter_cta', 'Stay Updated', 110);
    }

    private function seedBanners(Tenant $tenant): void
    {
        // Hero banners for hero placement
        $this->firstOrCreateBanner($tenant, 'Hero Banner 1', 'hero', 'electronics/banners/hero-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Hero Banner 2', 'hero', 'electronics/banners/hero-banner-2', 1);

        // Promo banners
        $this->firstOrCreateBanner($tenant, 'Promotional Banner 1', 'promo', 'electronics/banners/promo-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Flash Sale Banner', 'promo', 'electronics/banners/flash-sale-banner', 1);

        // Keep legacy hero for compat
        $this->firstOrCreateBanner($tenant, 'Electronics Hero', 'hero', 'electronics/hero', 10);
    }

    private function seedProducts(Tenant $tenant): void
    {
        // Resolve helpers
        $cat = fn (string $name): ?Category => Category::query()->where('tenant_id', $tenant->id)->where('name', $name)->first();
        $brand = fn (string $name): ?Brand => Brand::query()->where('tenant_id', $tenant->id)->where('slug', Str::slug($name))->first();

        $smartphones = $cat('Smartphones');
        $featurePhones = $cat('Feature Phones');
        $tablets = $cat('Tablets');
        $mobileAcc = $cat('Mobile Accessories');
        $watches = $cat('Smart Watches');
        $audio = $cat('Audio');
        $power = $cat('Power & Charging');
        $gadgets = $cat('Gadgets');

        $definitions = [
            // Smartphones - 10 as requested
            [
                'model_number' => 'ELEC-IP16-128',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Apple')?->id,
                'base_price' => 14900000, 'compare_at_price' => 15900000,
                'is_featured' => true, 'is_best_selling' => true, 'sold_count' => 152,
                'en' => ['name' => 'iPhone 16 128GB', 'slug' => 'iphone-16-128gb', 'description' => 'A18 chip, Super Retina XDR, advanced dual-camera system. Official PTA approved.'],
                'asset' => 'electronics/products/iphone-16',
                'variants' => [
                    ['sku' => 'ELEC-IP16-128-BLK', 'price' => 14900000, 'compare_at_price' => 15900000, 'attrs' => ['storage_capacity' => '128GB', 'device_color' => 'Black'], 'qty' => '15.000'],
                    ['sku' => 'ELEC-IP16-128-WHT', 'price' => 14900000, 'attrs' => ['storage_capacity' => '128GB', 'device_color' => 'White'], 'qty' => '10.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-IP16-PRO-256',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Apple')?->id,
                'base_price' => 18500000, 'compare_at_price' => 19500000,
                'is_featured' => true, 'is_best_selling' => true, 'sold_count' => 98,
                'en' => ['name' => 'iPhone 16 Pro 256GB', 'slug' => 'iphone-16-pro-256gb', 'description' => 'Titanium design, A18 Pro chip, pro camera system with 5x zoom.'],
                'asset' => 'electronics/products/iphone-16-pro',
                'variants' => [
                    ['sku' => 'ELEC-IP16-PRO-256-TIT', 'price' => 18500000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Titanium'], 'qty' => '8.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-S25-256',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Samsung')?->id,
                'base_price' => 13200000, 'compare_at_price' => 14200000,
                'is_featured' => true, 'is_best_selling' => true, 'sold_count' => 210,
                'en' => ['name' => 'Samsung Galaxy S25 256GB', 'slug' => 'samsung-galaxy-s25-256gb', 'description' => 'Snapdragon 8 Elite, 200MP camera, Galaxy AI, 5000mAh battery.'],
                'asset' => 'electronics/products/galaxy-s25',
                'variants' => [
                    ['sku' => 'ELEC-S25-256-BLK', 'price' => 13200000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Black'], 'qty' => '12.000'],
                    ['sku' => 'ELEC-S25-256-BLU', 'price' => 13200000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Blue'], 'qty' => '9.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-A56-128',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Samsung')?->id,
                'base_price' => 4550000, 'is_featured' => false, 'sold_count' => 75,
                'en' => ['name' => 'Samsung Galaxy A56 128GB', 'slug' => 'samsung-galaxy-a56-128gb', 'description' => 'Awesome camera, 6.6" Super AMOLED, IP67 water resistant.'],
                'asset' => 'electronics/products/galaxy-a56',
                'variants' => [
                    ['sku' => 'ELEC-A56-128-BLK', 'price' => 4550000, 'attrs' => ['storage_capacity' => '128GB', 'device_color' => 'Black'], 'qty' => '20.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-PIXEL9-128',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Google')?->id,
                'base_price' => 10500000, 'is_featured' => true, 'sold_count' => 44,
                'en' => ['name' => 'Google Pixel 9 128GB', 'slug' => 'google-pixel-9-128gb', 'description' => 'Google Tensor G4, best-in-class AI photography, 7 years updates.'],
                'asset' => 'electronics/products/pixel-9',
                'variants' => [
                    ['sku' => 'ELEC-PIXEL9-128-BLK', 'price' => 10500000, 'attrs' => ['storage_capacity' => '128GB', 'device_color' => 'Black'], 'qty' => '11.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-OP13-256',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('OnePlus')?->id,
                'base_price' => 9800000, 'compare_at_price' => 10800000,
                'is_featured' => true, 'sold_count' => 62,
                'en' => ['name' => 'OnePlus 13 256GB', 'slug' => 'oneplus-13-256gb', 'description' => 'Snapdragon 8 Elite, 100W SUPERVOOC, Hasselblad cameras.'],
                'asset' => 'electronics/products/oneplus-13',
                'variants' => [
                    ['sku' => 'ELEC-OP13-256-BLU', 'price' => 9800000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Blue'], 'qty' => '14.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-MI15-256',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Xiaomi')?->id,
                'base_price' => 8900000, 'is_featured' => true, 'sold_count' => 88,
                'en' => ['name' => 'Xiaomi 15 256GB', 'slug' => 'xiaomi-15-256gb', 'description' => 'Leica optics, Snapdragon 8 Elite, 90W HyperCharge.'],
                'asset' => 'electronics/products/xiaomi-15',
                'variants' => [
                    ['sku' => 'ELEC-MI15-256-WHT', 'price' => 8900000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'White'], 'qty' => '13.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-RN14-128',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Xiaomi')?->id,
                'base_price' => 2850000, 'is_featured' => false, 'is_best_selling' => true, 'sold_count' => 180,
                'en' => ['name' => 'Redmi Note 14 128GB', 'slug' => 'redmi-note-14-128gb', 'description' => '120Hz AMOLED, 108MP camera, 33W fast charging. Budget king.'],
                'asset' => 'electronics/products/redmi-note-14',
                'variants' => [
                    ['sku' => 'ELEC-RN14-128-BLU', 'price' => 2850000, 'attrs' => ['storage_capacity' => '128GB', 'device_color' => 'Blue'], 'qty' => '25.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-VV50-256',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Vivo')?->id,
                'base_price' => 4250000, 'is_featured' => false, 'sold_count' => 52,
                'en' => ['name' => 'vivo V50 256GB', 'slug' => 'vivo-v50-256gb', 'description' => 'ZEISS portrait, 6000mAh battery, 90W charging.'],
                'asset' => 'electronics/products/vivo-v50',
                'variants' => [
                    ['sku' => 'ELEC-VV50-256-BLK', 'price' => 4250000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Black'], 'qty' => '16.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-OPR13-256',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('OPPO')?->id,
                'base_price' => 4850000, 'is_featured' => false, 'sold_count' => 41,
                'en' => ['name' => 'OPPO Reno 13 256GB', 'slug' => 'oppo-reno-13-256gb', 'description' => 'AI portrait, 80W SUPERVOOC, ultra-slim design.'],
                'asset' => 'electronics/products/oppo-reno-13',
                'variants' => [
                    ['sku' => 'ELEC-OPR13-256-PNK', 'price' => 4850000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Pink'], 'qty' => '12.000'],
                ],
            ],
            // Feature Phones
            [
                'model_number' => 'ELEC-NK105',
                'category_id' => $featurePhones?->id,
                'brand_id' => $brand('Nokia')?->id,
                'base_price' => 280000, 'is_featured' => false, 'sold_count' => 95,
                'en' => ['name' => 'Nokia 105 (2024)', 'slug' => 'nokia-105-2024', 'description' => 'Classic bar phone, long battery, wireless FM radio.'],
                'asset' => 'electronics/products/nokia-105',
                'variants' => [
                    ['sku' => 'ELEC-NK105-BLK', 'price' => 280000, 'attrs' => ['device_color' => 'Black'], 'qty' => '30.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-NK2660',
                'category_id' => $featurePhones?->id,
                'brand_id' => $brand('Nokia')?->id,
                'base_price' => 850000, 'is_featured' => false, 'sold_count' => 33,
                'en' => ['name' => 'Nokia 2660 Flip', 'slug' => 'nokia-2660-flip', 'description' => 'Flip phone with big buttons, emergency SOS, 4G support.'],
                'asset' => 'electronics/products/nokia-2660-flip',
                'variants' => [
                    ['sku' => 'ELEC-NK2660-BLK', 'price' => 850000, 'attrs' => ['device_color' => 'Black'], 'qty' => '18.000'],
                ],
            ],
            // Tablets
            [
                'model_number' => 'ELEC-GTAB-A9',
                'category_id' => $tablets?->id,
                'brand_id' => $brand('Samsung')?->id,
                'base_price' => 6500000, 'is_featured' => true, 'sold_count' => 27,
                'en' => ['name' => 'Samsung Galaxy Tab A9 64GB', 'slug' => 'samsung-galaxy-tab-a9-64gb', 'description' => '8.7" display, dual speakers, 5100mAh battery.'],
                'asset' => 'electronics/products/galaxy-tab',
                'variants' => [
                    ['sku' => 'ELEC-GTAB-A9-GRY', 'price' => 6500000, 'attrs' => ['storage_capacity' => '64GB', 'device_color' => 'Graphite'], 'qty' => '10.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-XPAD-6',
                'category_id' => $tablets?->id,
                'brand_id' => $brand('Xiaomi')?->id,
                'base_price' => 3850000, 'is_featured' => false, 'sold_count' => 19,
                'en' => ['name' => 'Xiaomi Pad 6 128GB', 'slug' => 'xiaomi-pad-6-128gb', 'description' => '11" 144Hz display, Snapdragon 870, quad speakers.'],
                'asset' => 'electronics/products/xiaomi-pad',
                'variants' => [
                    ['sku' => 'ELEC-XPAD-6-BLU', 'price' => 3850000, 'attrs' => ['storage_capacity' => '128GB', 'device_color' => 'Blue'], 'qty' => '9.000'],
                ],
            ],
            // Audio
            [
                'model_number' => 'ELEC-TWS-APRO',
                'category_id' => $audio?->id,
                'brand_id' => $brand('Apple')?->id,
                'base_price' => 350000, 'compare_at_price' => 450000,
                'is_featured' => true, 'is_best_selling' => true, 'sold_count' => 240,
                'en' => ['name' => 'AirPods-style TWS Earbuds', 'slug' => 'airpods-style-tws-earbuds', 'description' => 'True wireless, ANC, 30h battery case.'],
                'asset' => 'electronics/products/tws-airpods-style',
                'variants' => [
                    ['sku' => 'ELEC-TWS-APRO-WHT', 'price' => 350000, 'attrs' => ['device_color' => 'White'], 'qty' => '40.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-TWS-GBUDS',
                'category_id' => $audio?->id,
                'brand_id' => $brand('Samsung')?->id,
                'base_price' => 450000, 'is_featured' => false, 'sold_count' => 110,
                'en' => ['name' => 'Galaxy Buds-style TWS', 'slug' => 'galaxy-buds-style-tws', 'description' => 'Hi-Fi sound, bass boost, touch control.'],
                'asset' => 'electronics/products/galaxy-buds-style',
                'variants' => [
                    ['sku' => 'ELEC-TWS-GBUDS-BLK', 'price' => 450000, 'attrs' => ['device_color' => 'Black'], 'qty' => '35.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-NECK-01',
                'category_id' => $audio?->id,
                'brand_id' => $brand('Realme')?->id,
                'base_price' => 180000, 'is_featured' => false, 'sold_count' => 78,
                'en' => ['name' => 'Bluetooth Neckband Earphone', 'slug' => 'bluetooth-neckband-earphone', 'description' => '20h playtime, magnetic earbuds, fast charge.'],
                'asset' => 'electronics/products/neckband-bluetooth',
                'variants' => [
                    ['sku' => 'ELEC-NECK-01-BLK', 'price' => 180000, 'attrs' => ['device_color' => 'Black'], 'qty' => '28.000'],
                ],
            ],
            // Charging
            [
                'model_number' => 'ELEC-CHG25W',
                'category_id' => $power?->id ?? $mobileAcc?->id,
                'brand_id' => $brand('Samsung')?->id,
                'base_price' => 150000, 'is_featured' => false, 'sold_count' => 165,
                'en' => ['name' => '25W Fast Charger (Type-C)', 'slug' => '25w-fast-charger-type-c', 'description' => 'USB-C PD fast charging, compact design, safety protection.'],
                'asset' => 'electronics/products/charger-25w',
                'variants' => [
                    ['sku' => 'ELEC-CHG25W-WHT', 'price' => 150000, 'attrs' => ['device_color' => 'White'], 'qty' => '50.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-CHG45W',
                'category_id' => $power?->id ?? $mobileAcc?->id,
                'brand_id' => $brand('Samsung')?->id,
                'base_price' => 220000, 'is_featured' => false, 'sold_count' => 92,
                'en' => ['name' => '45W Super Fast Charger', 'slug' => '45w-super-fast-charger', 'description' => 'Super fast charging 2.0 for Galaxy flagships.'],
                'asset' => 'electronics/products/charger-45w',
                'variants' => [
                    ['sku' => 'ELEC-CHG45W-BLK', 'price' => 220000, 'attrs' => ['device_color' => 'Black'], 'qty' => '32.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-CABLE-C1M',
                'category_id' => $mobileAcc?->id,
                'brand_id' => $brand('Realme')?->id,
                'base_price' => 55000, 'is_featured' => false, 'sold_count' => 310,
                'en' => ['name' => 'USB-C Cable 1M (Fast Charge)', 'slug' => 'usb-c-cable-1m-fast-charge', 'description' => 'Braided 60W PD, 480Mbps data, tangle-free.'],
                'asset' => 'electronics/products/cable-usb-c',
                'variants' => [
                    ['sku' => 'ELEC-CABLE-C1M-BLK', 'price' => 55000, 'attrs' => ['device_color' => 'Black'], 'qty' => '80.000'],
                ],
            ],
            // Power Banks
            [
                'model_number' => 'ELEC-PB10K',
                'category_id' => $power?->id,
                'brand_id' => $brand('Xiaomi')?->id,
                'base_price' => 185000, 'compare_at_price' => 220000,
                'is_featured' => true, 'sold_count' => 142,
                'en' => ['name' => '10000mAh Power Bank', 'slug' => '10000mah-power-bank', 'description' => '22.5W fast charge, dual output, compact.'],
                'asset' => 'electronics/products/powerbank-10000',
                'variants' => [
                    ['sku' => 'ELEC-PB10K-BLK', 'price' => 185000, 'attrs' => ['device_color' => 'Black'], 'qty' => '45.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-PB20K',
                'category_id' => $power?->id,
                'brand_id' => $brand('Xiaomi')?->id,
                'base_price' => 285000, 'is_featured' => false, 'sold_count' => 108,
                'en' => ['name' => '20000mAh Power Bank', 'slug' => '20000mah-power-bank', 'description' => '33W fast charge, LED display, triple output.'],
                'asset' => 'electronics/products/powerbank-20000',
                'variants' => [
                    ['sku' => 'ELEC-PB20K-BLK', 'price' => 285000, 'attrs' => ['device_color' => 'Black'], 'qty' => '38.000'],
                ],
            ],
            // Smart Watches
            [
                'model_number' => 'ELEC-SW-AMO1',
                'category_id' => $watches?->id,
                'brand_id' => $brand('Realme')?->id,
                'base_price' => 550000, 'compare_at_price' => 650000,
                'is_featured' => true, 'is_best_selling' => true, 'sold_count' => 195,
                'en' => ['name' => 'AMOLED Smart Watch', 'slug' => 'amoled-smart-watch', 'description' => '1.43" AMOLED, BT calling, SpO2, 7-day battery.'],
                'asset' => 'electronics/products/smartwatch-amoled',
                'variants' => [
                    ['sku' => 'ELEC-SW-AMO1-BLK', 'price' => 550000, 'attrs' => ['device_color' => 'Black'], 'qty' => '22.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-SW-BAND8',
                'category_id' => $watches?->id,
                'brand_id' => $brand('Xiaomi')?->id,
                'base_price' => 250000, 'is_featured' => false, 'sold_count' => 88,
                'en' => ['name' => 'Fitness Smart Band', 'slug' => 'fitness-smart-band', 'description' => '1.62" AMOLED, 14-day battery, 150+ sports modes.'],
                'asset' => 'electronics/products/fitness-band',
                'variants' => [
                    ['sku' => 'ELEC-SW-BAND8-BLK', 'price' => 250000, 'attrs' => ['device_color' => 'Black'], 'qty' => '27.000'],
                ],
            ],
            // Accessories
            [
                'model_number' => 'ELEC-CASE-PREM',
                'category_id' => $mobileAcc?->id ?? $gadgets?->id,
                'brand_id' => $brand('Nothing')?->id,
                'base_price' => 85000, 'is_featured' => false, 'sold_count' => 205,
                'en' => ['name' => 'Premium Phone Case (Shockproof)', 'slug' => 'premium-phone-case-shockproof', 'description' => 'Military-grade drop protection, clear back, raised edges.'],
                'asset' => 'electronics/products/phone-case-premium',
                'variants' => [
                    ['sku' => 'ELEC-CASE-PREM-BLK', 'price' => 85000, 'attrs' => ['device_color' => 'Black'], 'qty' => '60.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-GLASS-TG',
                'category_id' => $mobileAcc?->id,
                'brand_id' => $brand('Nothing')?->id,
                'base_price' => 45000, 'is_featured' => false, 'sold_count' => 280,
                'en' => ['name' => 'Tempered Glass Screen Protector', 'slug' => 'tempered-glass-screen-protector', 'description' => '9H hardness, HD clear, full glue, easy install.'],
                'asset' => 'electronics/products/tempered-glass',
                'variants' => [
                    ['sku' => 'ELEC-GLASS-TG-CLR', 'price' => 45000, 'attrs' => ['device_color' => 'White'], 'qty' => '90.000'],
                ],
            ],
        ];

        // Legacy products - keep for backward compatibility (tests & demo checks)
        $legacy = [
            [
                'model_number' => 'ELEC-IP15-128',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Apple')?->id,
                'base_price' => 13500000, 'is_featured' => true, 'sold_count' => 64,
                'en' => ['name' => 'iPhone 15 Pro 128GB', 'slug' => 'iphone-15-pro-128gb', 'description' => 'Titanium design, A17 Pro chip, pro camera system.'],
                'asset' => 'electronics/product-1',
                'variants' => [
                    ['sku' => 'ELEC-IP15-128-BLK', 'price' => 13500000, 'attrs' => ['storage_capacity' => '128GB', 'device_color' => 'Black'], 'qty' => '8.000'],
                    ['sku' => 'ELEC-IP15-256-WHT', 'price' => 14800000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'White'], 'qty' => '5.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-S24-256',
                'category_id' => $smartphones?->id,
                'brand_id' => $brand('Samsung')?->id,
                'base_price' => 12200000, 'is_featured' => true, 'sold_count' => 57,
                'en' => ['name' => 'Samsung Galaxy S24 Ultra 256GB', 'slug' => 'samsung-galaxy-s24-ultra-256gb', 'description' => '200MP camera, S Pen, Snapdragon 8 Gen 3.'],
                'asset' => 'electronics/product-2',
                'variants' => [
                    ['sku' => 'ELEC-S24-256-BLK', 'price' => 12200000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Black'], 'qty' => '10.000'],
                ],
            ],
            [
                'model_number' => 'ELEC-MBA-M2',
                'category_id' => $cat('Laptops')?->id ?? $cat('Laptop & Computer Accessories')?->id,
                'brand_id' => $brand('Apple')?->id,
                'base_price' => 18500000, 'is_featured' => true, 'sold_count' => 22,
                'en' => ['name' => 'MacBook Air M2 256GB', 'slug' => 'macbook-air-m2-256gb', 'description' => 'Supercharged by M2, 13.6-inch Liquid Retina display.'],
                'asset' => 'electronics/product-3',
                'variants' => [
                    ['sku' => 'ELEC-MBA-M2-256', 'price' => 18500000, 'attrs' => ['storage_capacity' => '256GB', 'device_color' => 'Titanium'], 'qty' => '4.000'],
                ],
            ],
        ];

        // Merge, but prioritize new definitions (legacy added last, firstOrCreate will not overwrite new if same model_number conflict - we keep distinct numbers)
        $all = array_merge($definitions, $legacy);

        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'hot-deals-flash-sale')->first();
        $hotDealSkus = ['ELEC-TWS-APRO-WHT', 'ELEC-PB10K-BLK', 'ELEC-SW-AMO1-BLK', 'ELEC-CHG25W-WHT', 'ELEC-RN14-128-BLU', 'ELEC-IP16-128-BLK'];

        foreach ($all as $def) {
            $asset = $def['asset'] ?? null;
            $product = $this->firstOrCreateProduct($tenant, [
                'model_number' => $def['model_number'],
                'category_id' => $def['category_id'],
                'brand_id' => $def['brand_id'],
                'base_price' => $def['base_price'],
                'is_featured' => (bool) ($def['is_featured'] ?? false),
                'sold_count' => (int) ($def['sold_count'] ?? 0),
                'is_official_import' => true,
            ], [
                'en' => $def['en'],
            ], $asset);

            // Ensure compare_at_price via direct update if provided (not fillable on firstOrCreate path for existing)
            if (isset($def['compare_at_price']) && $def['compare_at_price'] > ($product->getAttribute('base_price') ?? 0)) {
                // store compare_at on variants instead - product-level base_price is main; variant compare_at drives discount badge
            }

            foreach ($def['variants'] as $vDef) {
                $variant = $this->firstOrCreateVariant($tenant, $product, $vDef['sku'], (int) $vDef['price'], $asset);
                // Update compare_at_price if provided
                $compareAt = $vDef['compare_at_price'] ?? $def['compare_at_price'] ?? null;
                if ($compareAt !== null && $compareAt > (int) $variant->price) {
                    // Use direct DB update to avoid model fillable gap (price vs compare_at_price)
                    ProductVariant::query()->where('id', $variant->id)->update(['compare_at_price' => (int) $compareAt]);
                }
                // For hot deal campaign, ensure discounted
                if (in_array($vDef['sku'], $hotDealSkus, true) && $variant->compare_at_price === null) {
                    $inflated = (int) round((int) $variant->price * 1.22);
                    ProductVariant::query()->where('id', $variant->id)->update(['compare_at_price' => $inflated]);
                }
                $this->attachVariantAttributes($tenant, $variant, $vDef['attrs'] ?? []);
                $this->ensureStock($tenant, $variant, (string) ($vDef['qty'] ?? '10.000'));
            }

            // Attach to Hot Deals campaign if eligible product
            if ($campaign) {
                $isHot = false;
                foreach ($def['variants'] as $vd) {
                    if (in_array($vd['sku'], $hotDealSkus, true)) {
                        $isHot = true;
                        break;
                    }
                }
                if ($isHot) {
                    // attach campaign_product pivot idempotent
                    $exists = DB::table('campaign_product')->where('campaign_id', $campaign->id)->where('product_id', $product->id)->exists();
                    if (! $exists) {
                        $max = DB::table('campaign_product')->where('campaign_id', $campaign->id)->max('sort_order');
                        DB::table('campaign_product')->insert([
                            'campaign_id' => $campaign->id,
                            'product_id' => $product->id,
                            'sort_order' => (int) $max + 1,
                        ]);
                    }
                }
            }
        }
    }

    private function seedMenus(Tenant $tenant): void
    {
        $menu = Menu::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'location' => MenuLocation::Header->value],
            ['name' => 'Main Header Menu']
        );

        $mk = function (string $label, string $linkType, ?string $linkValue, int $sort, ?int $parentId = null, ?string $badge = null) use ($tenant, $menu): MenuItem {
            // Use label+menu_id+parent_id as natural key to stay idempotent
            $item = MenuItem::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'menu_id' => $menu->id, 'label' => $label, 'parent_id' => $parentId],
                ['link_type' => $linkType, 'link_value' => $linkValue, 'visibility' => Visibility::All, 'sort_order' => $sort, 'badge_text' => $badge]
            );
            // Update link if changed (keep idempotent but allow correction)
            $currentType = $item->link_type instanceof LinkType ? $item->link_type->value : (string) $item->link_type;
            if ($currentType !== $linkType || $item->link_value !== $linkValue) {
                $item->update(['link_type' => $linkType, 'link_value' => $linkValue]);
            }

            return $item;
        };

        // Top-level: Home, Smartphones, Accessories, Brands, Deals/Offers
        $home = $mk('Home', LinkType::External->value, '/', 10);
        $smartphones = $mk('Smartphones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Smartphones') ?? '/', 20);
        $accessories = $mk('Accessories', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Mobile Accessories') ?? '/', 30);
        $brands = $mk('Brands', LinkType::External->value, '/brands', 40);
        $mk('Blog', LinkType::BlogIndex->value, null, 45);
        $mk('Deals/Offers', LinkType::External->value, '/offers', 50, null, '🔥');

        // Smartphones dropdown
        $mk('All Smartphones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Smartphones') ?? '/', 11, $smartphones->id);
        $mk('Android Phones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Android Phones') ?? '/', 12, $smartphones->id);
        $mk('iPhone', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'iPhone') ?? '/', 13, $smartphones->id);
        $mk('Gaming Phones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Gaming Phones') ?? '/', 14, $smartphones->id);
        $mk('Foldable Phones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Foldable Phones') ?? '/', 15, $smartphones->id);
        $mk('Feature Phones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Feature Phones') ?? '/', 16, $smartphones->id);

        // Accessories dropdown
        $mk('Chargers', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Chargers & Adapters') ?? '/', 31, $accessories->id);
        $mk('Cables', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'USB Cables') ?? '/', 32, $accessories->id);
        $mk('Power Banks', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Power Banks') ?? '/', 33, $accessories->id);
        $mk('TWS & Earphones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'TWS & Earphones') ?? '/', 34, $accessories->id);
        $mk('Headphones', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Headphones') ?? '/', 35, $accessories->id);
        $mk('Cases & Covers', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Cases & Covers') ?? '/', 36, $accessories->id);
        $mk('Screen Protectors', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Screen Protectors') ?? '/', 37, $accessories->id);
        $mk('Smart Watches', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Smart Watches') ?? '/', 38, $accessories->id);

        // Brands dropdown (logo/grid) - 9
        foreach (['Apple', 'Samsung', 'Xiaomi', 'OnePlus', 'Vivo', 'OPPO', 'Realme', 'Google', 'Nothing'] as $idx => $bName) {
            $slug = Str::slug($bName);
            $mk($bName, LinkType::Brand->value, $slug, 41 + $idx, $brands->id);
        }
    }

    private function categorySlugForMenu(Tenant $tenant, string $name): ?string
    {
        $cat = Category::query()->where('tenant_id', $tenant->id)->where('name', $name)->first();
        if (! $cat) {
            $cat = Category::query()->where('tenant_id', $tenant->id)->where('name', trim($name))->first();
        }

        return $cat?->slug;
    }

    private function seedSupportDefaults(Tenant $tenant): void
    {
        // Trust content (policies + FAQs) - idempotent via TrustContentSeeder contract
        try {
            app(TrustContentSeeder::class)->run($tenant);
        } catch (\Throwable $e) {
        }

        // Payment methods
        PaymentMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'cod'],
            ['name' => 'Cash on Delivery', 'display_name' => 'Cash on Delivery', 'type' => PaymentMethodType::Cod, 'is_active' => true, 'sort_order' => 1, 'gateway_ownership' => 'shop']
        );
        PaymentMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'bkash_manual'],
            ['name' => 'bKash', 'display_name' => 'bKash Send Money', 'type' => PaymentMethodType::ManualMfs, 'provider' => 'bKash', 'account_number' => '01XXXXXXXXX', 'account_name' => 'Merchant bKash', 'instructions' => 'Send money to the number above and submit your Transaction ID on the confirmation page.', 'requires_verification' => true, 'is_active' => true, 'sort_order' => 2, 'gateway_ownership' => 'shop']
        );
        PaymentMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'nagad_manual'],
            ['name' => 'Nagad', 'display_name' => 'Nagad Send Money', 'type' => PaymentMethodType::ManualMfs, 'provider' => 'Nagad', 'account_number' => '01XXXXXXXXX', 'account_name' => 'Merchant Nagad', 'instructions' => 'Send money to the Nagad number and submit Transaction ID.', 'requires_verification' => true, 'is_active' => true, 'sort_order' => 3, 'gateway_ownership' => 'shop']
        );
        PaymentMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'bank_transfer'],
            ['name' => 'Bank Transfer', 'display_name' => 'Bank Transfer', 'type' => PaymentMethodType::BankTransfer, 'provider' => 'Bank', 'bank_name' => 'Dutch-Bangla Bank', 'account_number' => '0000000000', 'instructions' => 'Transfer to the bank account and submit reference.', 'requires_verification' => true, 'is_active' => true, 'sort_order' => 4, 'gateway_ownership' => 'shop']
        );

        // Shipping methods
        ShippingMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Standard Delivery'],
            ['type' => ShippingMethodType::FlatRate, 'cost' => 6000, 'is_active' => true, 'sort_order' => 1]
        );
        ShippingMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Express Delivery (Inside Dhaka)'],
            ['type' => ShippingMethodType::FlatRate, 'cost' => 12000, 'is_active' => true, 'sort_order' => 2]
        );
        ShippingMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Store Pickup'],
            ['type' => ShippingMethodType::Pickup, 'cost' => 0, 'is_active' => true, 'sort_order' => 3]
        );

        // Outlet demo
        Outlet::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'main-outlet-gulshan'],
            [
                'name' => 'Main Outlet - Gulshan',
                'address_line_1' => 'House 123, Road 11, Gulshan-1',
                'city' => 'Dhaka',
                'phone' => '01XXXXXXXXX',
                'email' => 'support@'.$tenant->subdomain.'.test',
                'opening_hours' => ['sat-thu' => '10:00 AM - 9:00 PM', 'fri' => 'Closed'],
                'latitude' => '23.7806',
                'longitude' => '90.4070',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
        Outlet::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'dhanmondi-outlet'],
            [
                'name' => 'Dhanmondi Outlet',
                'address_line_1' => 'House 45, Road 27, Dhanmondi',
                'city' => 'Dhaka',
                'phone' => '01XXXXXXXXX',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }

    private function firstOrCreateCategory(Tenant $tenant, string $name, ?int $parentId = null, ?string $imageStem = null): Category
    {
        $category = Category::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name, 'parent_id' => $parentId],
            ['slug' => Str::slug($name)]
        );

        if ($imageStem !== null) {
            $this->ensureCategoryImage($category, $imageStem);
        }

        return $category;
    }

    private function ensureCategoryImage(Category $category, string $stem, bool $force = false): void
    {
        $hasImage = $category->image_path !== null && $category->image_path !== '';
        $currentDest = $hasImage ? storage_path('app/public/'.$category->image_path) : null;
        $currentIsPlaceholder = false;
        if ($hasImage && is_file($currentDest)) {
            $size = @filesize($currentDest);
            $currentIsPlaceholder = $size !== false && $size < 50000;
        } elseif ($hasImage && ! is_file($currentDest)) {
            $currentIsPlaceholder = true;
        }

        if ($hasImage && ! $force && ! $currentIsPlaceholder && is_file($currentDest)) {
            // Respect merchant-edited image (real image already present)
            return;
        }

        $src = $this->resolveAssetPath($stem);
        if ($src === null) {
            $src = $this->resolveAssetPath('electronics/categories/smartphones');
        }
        if ($src === null || ! is_file($src)) {
            return;
        }

        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'jpg';
        }
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $dir = storage_path('app/public/category-images');
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $filename = Str::slug($category->name.'-'.$category->id).'.'.$ext;
        $dest = $dir.'/'.$filename;

        // Handle extension change: remove old file with different ext
        if ($hasImage && $category->image_path !== 'category-images/'.$filename && is_file($currentDest)) {
            // If old file is placeholder or force, remove it
            if ($currentIsPlaceholder || $force) {
                @unlink($currentDest);
            }
        }

        $shouldCopy = ! is_file($dest) || $currentIsPlaceholder || $force;
        if ($shouldCopy) {
            @copy($src, $dest);
        }

        if (is_file($dest) && $category->image_path !== 'category-images/'.$filename) {
            $category->update(['image_path' => 'category-images/'.$filename]);
        } elseif (is_file($dest) && ! $hasImage) {
            $category->update(['image_path' => 'category-images/'.$filename]);
        }
    }

    private function firstOrCreateBrand(Tenant $tenant, string $name): Brand
    {
        return Brand::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => Str::slug($name)],
            ['name' => $name]
        );
    }

    private function ensureBrandLogo(Brand $brand, string $stem, bool $force = false): void
    {
        $hasLogo = $brand->logo_path !== null && $brand->logo_path !== '';
        $currentDest = $hasLogo ? storage_path('app/public/'.$brand->logo_path) : null;
        $currentIsPlaceholder = false;
        if ($hasLogo && is_file($currentDest)) {
            $size = @filesize($currentDest);
            $currentIsPlaceholder = $size !== false && $size < 50000;
        } elseif ($hasLogo && ! is_file($currentDest)) {
            $currentIsPlaceholder = true;
        }

        if ($hasLogo && ! $force && ! $currentIsPlaceholder && is_file($currentDest)) {
            return;
        }

        $src = $this->resolveAssetPath($stem);
        if ($src === null || ! is_file($src)) {
            return;
        }

        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'png';
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'png';
        }

        $dir = storage_path('app/public/brand-logos');
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $filename = Str::slug($brand->slug).'.'.$ext;
        $dest = $dir.'/'.$filename;

        if ($hasLogo && $brand->logo_path !== 'brand-logos/'.$filename && is_file($currentDest) && ($currentIsPlaceholder || $force)) {
            @unlink($currentDest);
        }

        $shouldCopy = ! is_file($dest) || $currentIsPlaceholder || $force;
        if ($shouldCopy) {
            @copy($src, $dest);
        }

        if (is_file($dest)) {
            $brand->update(['logo_path' => 'brand-logos/'.$filename]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $options
     */
    private function firstOrCreateAttribute(Tenant $tenant, array $attributes, array $options): AttributeDefinition
    {
        $definition = AttributeDefinition::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $attributes['code']],
            [
                'label' => $attributes['label'],
                'data_type' => $attributes['data_type'],
                'unit' => $attributes['unit'],
                'group' => $attributes['group'],
                'is_filterable' => $attributes['is_filterable'],
                'is_variant_defining' => $attributes['is_variant_defining'],
                'sort_order' => $attributes['sort_order'],
                'is_global' => false,
            ]
        );

        foreach ($options as $idx => $value) {
            AttributeOption::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'attribute_definition_id' => $definition->id, 'value' => $value],
                ['label' => $value, 'sort_order' => $idx + 1]
            );
        }

        return $definition;
    }

    private function firstOrCreateHomepageSection(Tenant $tenant, string $type, string $title, int $sortOrder, array $config = []): HomepageSection
    {
        $section = HomepageSection::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'type' => $type, 'title' => $title],
            [
                'config' => $config,
                'visibility' => Visibility::All,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );

        // Keep config & sort_order in sync if merchant hasn't customized title
        $needsUpdate = false;
        $updates = [];
        if (($section->config ?? []) !== $config && $config !== []) {
            // Only patch if config is empty/default - don't overwrite merchant edits with non-empty config
            $existingConfig = $section->config ?? [];
            if ($existingConfig === [] || $existingConfig === null) {
                $updates['config'] = $config;
                $needsUpdate = true;
            }
        }
        if ((int) $section->sort_order !== $sortOrder) {
            // Preserve merchant reordering? Original test expects banner before trust before product
            // Only correct if still at old values 1,2,3,4 - migrate gap
            if (in_array((int) $section->sort_order, [1, 2, 3, 4], true)) {
                $updates['sort_order'] = $sortOrder;
                $needsUpdate = true;
            }
        }
        if ($needsUpdate) {
            $section->update($updates);
        }

        return $section->fresh() ?? $section;
    }

    private function firstOrCreateBanner(Tenant $tenant, string $title, string $placement, string $assetStem, int $sortOrder = 0): Banner
    {
        $banner = Banner::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => $title, 'placement' => $placement],
            [
                'media_type' => 'image',
                'visibility' => Visibility::All,
                'link_type' => 'none',
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );

        $path = $this->resolveAssetPath($assetStem);
        if ($path !== null && is_file($path)) {
            $hasMedia = $banner->getFirstMediaUrl('image') !== '';
            $needs = ! $hasMedia;
            if ($hasMedia) {
                $media = $banner->getFirstMedia('image');
                if ($media && $media->size < 50000) {
                    $needs = true;
                }
            }
            if ($needs) {
                if ($hasMedia) {
                    $banner->clearMediaCollection('image');
                }
                try {
                    $banner->addMedia($path)->preservingOriginal()->toMediaCollection('image');
                } catch (\Throwable $e) {
                }
            }
        }

        return $banner;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  array<string, array{ name: string, slug: string, description?: string }>  $translations
     */
    private function firstOrCreateProduct(Tenant $tenant, array $attrs, array $translations, ?string $assetStem = null): Product
    {
        $product = Product::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'model_number' => $attrs['model_number']],
            array_merge([
                'status' => ProductStatus::Published,
                'type' => 'simple',
                'published_at' => now(),
            ], $attrs)
        );

        // For existing products, ensure sold_count / is_featured updated if empty (preserve merchant edits otherwise)
        $patch = [];
        if (isset($attrs['sold_count']) && (int) ($product->sold_count ?? 0) === 0) {
            $patch['sold_count'] = $attrs['sold_count'];
        }
        if (isset($attrs['is_featured']) && ! $product->is_featured && $attrs['is_featured']) {
            $patch['is_featured'] = true;
        }
        if ($patch !== []) {
            $product->update($patch);
        }

        foreach ($translations as $locale => $data) {
            ProductTranslation::query()->firstOrCreate(
                ['product_id' => $product->id, 'locale' => $locale],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'slug' => $data['slug'] ?? Str::slug($data['name']),
                    'description' => $data['description'] ?? null,
                ]
            );
        }

        if ($assetStem !== null) {
            $path = $this->resolveAssetPath($assetStem);
            if ($path !== null && is_file($path)) {
                $hasMedia = $product->getFirstMediaUrl('images') !== '';
                $needs = ! $hasMedia;
                if ($hasMedia) {
                    $media = $product->getFirstMedia('images');
                    if ($media && $media->size < 50000) {
                        $needs = true;
                    }
                }
                if ($needs) {
                    if ($hasMedia) {
                        $product->clearMediaCollection('images');
                    }
                    try {
                        $product->addMedia($path)->preservingOriginal()->toMediaCollection('images');
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        return $product;
    }

    private function firstOrCreateVariant(Tenant $tenant, Product $product, string $sku, int $price, ?string $assetStem = null): ProductVariant
    {
        $variant = ProductVariant::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => $sku],
            [
                'product_id' => $product->id,
                'price' => $price,
                'is_active' => true,
            ]
        );

        if ($assetStem !== null) {
            $path = $this->resolveAssetPath($assetStem);
            if ($path !== null && is_file($path)) {
                $hasMedia = $variant->getFirstMediaUrl('images') !== '';
                $needs = ! $hasMedia;
                if ($hasMedia) {
                    $media = $variant->getFirstMedia('images');
                    if ($media && $media->size < 50000) {
                        $needs = true;
                    }
                }
                if ($needs) {
                    if ($hasMedia) {
                        $variant->clearMediaCollection('images');
                    }
                    try {
                        $variant->addMedia($path)->preservingOriginal()->toMediaCollection('images');
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        return $variant;
    }

    private function attachVariantAttributes(Tenant $tenant, ProductVariant $variant, array $codeToValue): void
    {
        foreach ($codeToValue as $code => $value) {
            $def = AttributeDefinition::query()->where('tenant_id', $tenant->id)->where('code', $code)->first();
            if (! $def) {
                continue;
            }
            $opt = AttributeOption::query()->where('tenant_id', $tenant->id)->where('attribute_definition_id', $def->id)->where('value', $value)->first();
            if (! $opt) {
                continue;
            }
            ProductAttributeValue::query()->firstOrCreate(
                ['product_variant_id' => $variant->id, 'attribute_definition_id' => $def->id],
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $variant->product_id,
                    'attribute_option_id' => $opt->id,
                ]
            );
        }
    }

    private function ensureStock(Tenant $tenant, ProductVariant $variant, string $qty): void
    {
        $location = Location::query()->where('tenant_id', $tenant->id)->where('is_default', true)->first();
        if (! $location) {
            $location = Location::query()->where('tenant_id', $tenant->id)->first();
        }
        if (! $location) {
            return;
        }

        $stock = StockItem::query()->firstOrCreate(
            ['product_variant_id' => $variant->id, 'location_id' => $location->id],
            ['tenant_id' => $tenant->id, 'quantity' => $qty, 'reserved_quantity' => '0.000']
        );

        if (bccomp((string) $stock->quantity, '0.000', 3) === 0 && bccomp($qty, '0.000', 3) !== 0) {
            $stock->update(['quantity' => $qty]);
        }
    }

    /**
     * Resolve starter asset path supporting jpg/jpeg/png/webp, any case,
     * with or without extension, hyphen/underscore agnostic.
     */
    private function resolveAssetPath(string $stem): ?string
    {
        $stem = ltrim($stem, '/');
        $exact = base_path('resources/starter-assets/'.$stem);
        if (is_file($exact)) {
            return $exact;
        }

        // Short stem without directory (e.g. 'gadgets' from category seeder) - try industry prefixes
        if (! str_contains($stem, '/')) {
            $prefixes = [
                'electronics/categories/'.$stem,
                'electronics/products/'.$stem,
                'electronics/brands/'.$stem,
                'electronics/banners/'.$stem,
                'grocery/categories/'.$stem,
                'grocery/products/'.$stem,
            ];
            foreach ($prefixes as $prefixed) {
                $found = $this->resolveAssetPath($prefixed);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        $dir = dirname(base_path('resources/starter-assets/'.$stem));
        $base = basename($stem);
        $baseWithoutExt = $base;
        if (str_contains($base, '.')) {
            $baseWithoutExt = pathinfo($base, PATHINFO_FILENAME);
        }

        $candidates = [];
        if (is_dir($dir)) {
            $files = scandir($dir);
            if ($files !== false) {
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    $candidatePath = $dir.'/'.$f;
                    if (! is_file($candidatePath)) {
                        continue;
                    }
                    $candidateBase = pathinfo($f, PATHINFO_FILENAME);
                    $candidateExt = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (! in_array($candidateExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        continue;
                    }
                    // Direct case-insensitive match on full basename without ext
                    if (strcasecmp($candidateBase, $baseWithoutExt) === 0 || strcasecmp($candidateBase, $base) === 0) {
                        $candidates[] = $candidatePath;

                        continue;
                    }
                    // Hyphen/underscore agnostic fallback
                    $normCandidate = str_replace(['-', '_'], '', strtolower($candidateBase));
                    $normStem = str_replace(['-', '_'], '', strtolower($baseWithoutExt));
                    if ($normCandidate === $normStem) {
                        $candidates[] = $candidatePath;
                    }
                }
            }
        }

        if ($candidates !== []) {
            usort($candidates, function (string $a, string $b): int {
                $sizeA = @filesize($a) ?: 0;
                $sizeB = @filesize($b) ?: 0;
                if ($sizeA !== $sizeB) {
                    return $sizeB <=> $sizeA;
                }
                $priority = ['webp' => 4, 'png' => 3, 'jpeg' => 2, 'jpg' => 1];

                return ($priority[strtolower(pathinfo($b, PATHINFO_EXTENSION))] ?? 0) <=> ($priority[strtolower(pathinfo($a, PATHINFO_EXTENSION))] ?? 0);
            });

            return $candidates[0];
        }

        // Fallback to legacy loop for exact stem + extension
        $extensions = ['.jpg', '.jpeg', '.png', '.webp', '.JPG', '.JPEG', '.PNG', '.WEBP'];
        foreach ($extensions as $ext) {
            $candidate = base_path('resources/starter-assets/'.$stem.$ext);
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        if (isset($baseWithoutExt) && $baseWithoutExt !== $base) {
            foreach ($extensions as $ext) {
                $candidate = base_path('resources/starter-assets/'.dirname($stem).'/'.$baseWithoutExt.$ext);
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
