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

final class FashionIndustrySeeder
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
        $preset = ThemePresets::forIndustry('fashion');
        $existing = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->first();
        if ($existing === null) {
            StoreThemeSetting::query()->create(['tenant_id' => $tenant->id, 'primary_color' => $preset['primary'], 'secondary_color' => $preset['secondary']]);

            return;
        }
        $updates = [];
        if ($existing->getAttribute('primary_color') === null || $existing->getAttribute('primary_color') === '') {
            $updates['primary_color'] = $preset['primary'];
        }
        if ($existing->getAttribute('secondary_color') === null || $existing->getAttribute('secondary_color') === '') {
            $updates['secondary_color'] = $preset['secondary'];
        }
        if ($updates !== []) {
            $existing->update($updates);
        }
    }

    private function seedCategories(Tenant $tenant): void
    {
        // Legacy for test compat
        $mensLegacy = $this->firstOrCreateCategory($tenant, "Men's Fashion", null, 'mens-fashion', 'fashion/categories/men');
        $womensLegacy = $this->firstOrCreateCategory($tenant, "Women's Fashion", null, 'womens-fashion', 'fashion/categories/women');
        $accessoriesLegacy = $this->firstOrCreateCategory($tenant, 'Accessories', null, 'accessories', 'fashion/categories/accessories');
        $this->firstOrCreateCategory($tenant, 'Shirts', $mensLegacy->id, 'shirts', 'fashion/categories/shirts');
        $this->firstOrCreateCategory($tenant, 'Dresses', $womensLegacy->id, 'dresses', 'fashion/categories/dresses');

        // Women
        $women = $this->firstOrCreateCategory($tenant, 'Women', null, 'women', 'fashion/categories/women');
        $womensClothing = $this->firstOrCreateCategory($tenant, "Women's Clothing", $women->id, 'womens-clothing', 'fashion/categories/womens-clothing');
        $this->firstOrCreateCategory($tenant, 'Saree', $womensClothing->id, 'saree', 'fashion/categories/saree');
        $this->firstOrCreateCategory($tenant, 'Salwar Kameez', $womensClothing->id, 'salwar-kameez', 'fashion/categories/salwar-kameez');
        $this->firstOrCreateCategory($tenant, 'Kurti', $womensClothing->id, 'kurti', 'fashion/categories/kurti');
        $this->firstOrCreateCategory($tenant, 'Tops', $womensClothing->id, 'tops', 'fashion/categories/tops');
        $this->firstOrCreateCategory($tenant, 'T-Shirts', $womensClothing->id, 't-shirts-women', 'fashion/categories/t-shirts');
        $this->firstOrCreateCategory($tenant, 'Dresses ', $womensClothing->id, 'dresses-women', 'fashion/categories/dresses');
        $this->firstOrCreateCategory($tenant, 'Jeans & Pants', $womensClothing->id, 'jeans-pants-women', 'fashion/categories/jeans-pants');
        $this->firstOrCreateCategory($tenant, 'Skirts', $womensClothing->id, 'skirts', 'fashion/categories/skirts');
        $this->firstOrCreateCategory($tenant, 'Hijab & Modest Wear', $womensClothing->id, 'hijab-modest-wear', 'fashion/categories/hijab-modest-wear');
        $this->firstOrCreateCategory($tenant, "Women's Footwear", $women->id, 'womens-footwear', 'fashion/categories/womens-footwear');
        $this->firstOrCreateCategory($tenant, "Women's Bags", $women->id, 'womens-bags', 'fashion/categories/womens-bags');
        $this->firstOrCreateCategory($tenant, "Women's Accessories", $women->id, 'womens-accessories', 'fashion/categories/womens-accessories');

        // Men
        $men = $this->firstOrCreateCategory($tenant, 'Men', null, 'men', 'fashion/categories/men');
        $mensClothing = $this->firstOrCreateCategory($tenant, "Men's Clothing", $men->id, 'mens-clothing', 'fashion/categories/mens-clothing');
        $this->firstOrCreateCategory($tenant, 'Panjabi', $mensClothing->id, 'panjabi', 'fashion/categories/panjabi');
        $this->firstOrCreateCategory($tenant, 'Shirts ', $mensClothing->id, 'shirts-men', 'fashion/categories/shirts');
        $this->firstOrCreateCategory($tenant, 'T-Shirts ', $mensClothing->id, 't-shirts-men', 'fashion/categories/t-shirts-men');
        $this->firstOrCreateCategory($tenant, 'Polo Shirts', $mensClothing->id, 'polo-shirts', 'fashion/categories/polo-shirts');
        $this->firstOrCreateCategory($tenant, 'Jeans & Pants ', $mensClothing->id, 'jeans-pants-men', 'fashion/categories/jeans-pants-men');
        $this->firstOrCreateCategory($tenant, 'Formal Wear', $mensClothing->id, 'formal-wear', 'fashion/categories/formal-wear');
        $this->firstOrCreateCategory($tenant, 'Hoodies & Sweatshirts', $mensClothing->id, 'hoodies-sweatshirts', 'fashion/categories/hoodies-sweatshirts');
        $this->firstOrCreateCategory($tenant, 'Shorts', $mensClothing->id, 'shorts', 'fashion/categories/shorts');
        $this->firstOrCreateCategory($tenant, "Men's Footwear", $men->id, 'mens-footwear', 'fashion/categories/mens-footwear');
        $this->firstOrCreateCategory($tenant, "Men's Accessories", $men->id, 'mens-accessories', 'fashion/categories/mens-accessories');

        // Kids
        $kids = $this->firstOrCreateCategory($tenant, 'Kids', null, 'kids', 'fashion/categories/kids');
        $this->firstOrCreateCategory($tenant, "Boys' Clothing", $kids->id, 'boys-clothing', 'fashion/categories/boys-clothing');
        $this->firstOrCreateCategory($tenant, "Girls' Clothing", $kids->id, 'girls-clothing', 'fashion/categories/girls-clothing');
        $this->firstOrCreateCategory($tenant, "Kids' Footwear", $kids->id, 'kids-footwear', 'fashion/categories/kids-footwear');
        $this->firstOrCreateCategory($tenant, "Kids' Accessories", $kids->id, 'kids-accessories', 'fashion/categories/kids-accessories');

        // Accessories top-level (reuse legacy Accessories for children)
        $this->firstOrCreateCategory($tenant, 'Watches', $accessoriesLegacy->id, 'watches', 'fashion/categories/watches');
        $this->firstOrCreateCategory($tenant, 'Sunglasses', $accessoriesLegacy->id, 'sunglasses', 'fashion/categories/sunglasses');
        $this->firstOrCreateCategory($tenant, 'Belts', $accessoriesLegacy->id, 'belts', 'fashion/categories/belts');
        $this->firstOrCreateCategory($tenant, 'Wallets', $accessoriesLegacy->id, 'wallets', 'fashion/categories/wallets');
        $this->firstOrCreateCategory($tenant, 'Caps & Hats', $accessoriesLegacy->id, 'caps-hats', 'fashion/categories/caps-hats');
        $this->firstOrCreateCategory($tenant, 'Jewelry', $accessoriesLegacy->id, 'jewelry', 'fashion/categories/jewelry');
        $this->firstOrCreateCategory($tenant, 'Scarves', $accessoriesLegacy->id, 'scarves', 'fashion/categories/scarves');
    }

    private function seedBrands(Tenant $tenant): void
    {
        $brands = [
            'Zara' => 'fashion/brands/zara',
            'Nike' => 'fashion/brands/nike',
            'Aarong' => 'fashion/brands/aarong',
            'Yellow' => 'fashion/brands/yellow',
            'Infinity' => 'fashion/brands/infinity',
            'Sailor' => 'fashion/brands/sailor',
        ];
        foreach ($brands as $name => $stem) {
            $brand = $this->firstOrCreateBrand($tenant, $name);
            $this->ensureBrandLogo($brand, $stem);
        }
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, ['code' => 'size', 'label' => 'Size', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 1], ['XS', 'S', 'M', 'L', 'XL', 'XXL']);
        $this->firstOrCreateAttribute($tenant, ['code' => 'fabric_color', 'label' => 'Color', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Appearance', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 2], ['Black', 'White', 'Red', 'Blue', 'Green', 'Beige', 'Maroon', 'Navy', 'Pink']);
        $this->firstOrCreateAttribute($tenant, ['code' => 'material', 'label' => 'Material', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => false, 'sort_order' => 3], ['Cotton', 'Polyester', 'Linen', 'Denim', 'Silk', 'Georgette']);
    }

    private function seedCampaigns(Tenant $tenant): void
    {
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'fashion-hot-deals'],
            [
                'name' => 'Fashion Hot Deals',
                'description' => 'Trending fashion at up to 40% OFF - Eid & Winter collections!',
                'accent_color' => '#ec4899',
                'short_tagline' => 'Up to 40% OFF',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(7),
            ]
        );
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'new-arrivals-fashion'],
            [
                'name' => 'New Arrivals',
                'description' => 'Just arrived - latest Saree, Kurti & Panjabi collections',
                'accent_color' => '#8b5cf6',
                'short_tagline' => 'Just Arrived',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->addDays(20),
            ]
        );
    }

    private function seedHomepage(Tenant $tenant): void
    {
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Hero Banner', 10, ['placement' => 'hero']);
        $this->firstOrCreateHomepageSection($tenant, 'trust_badges', 'Our Promise', 20);
        $mainCats = Category::query()->where('tenant_id', $tenant->id)->whereNull('parent_id')->whereIn('slug', ['women', 'men', 'kids', 'accessories', 'womens-fashion', 'mens-fashion'])->orderBy('id')->pluck('id')->all();
        $catConfig = ['source' => 'category', 'limit' => 12];
        if ($mainCats !== []) {
            $catConfig['category_ids'] = $mainCats;
        }
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Category', 30, $catConfig);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Trending Now', 40, ['data_source' => 'featured', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Brand', 50, ['source' => 'brand', 'limit' => 6]);
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'fashion-hot-deals')->first();
        $hot = $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Hot Deals - Fashion Sale', 60, ['data_source' => 'campaign', 'limit' => 8]);
        if ($campaign && $hot->campaign_id !== $campaign->id) {
            $hot->update(['campaign_id' => $campaign->id]);
        }
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'New Arrivals', 70, ['data_source' => 'latest', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Best Selling Products', 80, ['data_source' => 'best_selling', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Promotional Banner', 90, ['placement' => 'promo']);
        $this->firstOrCreateHomepageSection($tenant, 'blog_grid', 'Journal', 100, ['limit' => 3]);
        $this->firstOrCreateHomepageSection($tenant, 'newsletter_cta', 'Stay Updated', 110);
    }

    private function seedBanners(Tenant $tenant): void
    {
        $this->firstOrCreateBanner($tenant, 'Hero Banner 1', 'hero', 'fashion/banners/hero-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Hero Banner 2', 'hero', 'fashion/banners/hero-banner-2', 1);
        $this->firstOrCreateBanner($tenant, 'Promotional Banner 1', 'promo', 'fashion/banners/promo-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Flash Sale Banner', 'promo', 'fashion/banners/flash-sale-banner', 1);
        $this->firstOrCreateBanner($tenant, 'Fashion Hero', 'hero', 'fashion/hero', 10);
    }

    private function seedProducts(Tenant $tenant): void
    {
        $cat = fn (string $name): ?Category => Category::query()->where('tenant_id', $tenant->id)->where('name', $name)->first();
        $brand = fn (string $name): ?Brand => Brand::query()->where('tenant_id', $tenant->id)->where('slug', Str::slug($name))->first();
        $brandZara = $brand('Zara') ?? $brand('Aarong');
        $brandNike = $brand('Nike') ?? $brandZara;
        $brandAarong = $brand('Aarong') ?? $brandZara;

        $definitions = [
            [
                'model_number' => 'FASH-SAREE-SILK',
                'category_id' => $cat('Saree')?->id ?? $cat("Women's Clothing")?->id,
                'brand_id' => $brandAarong?->id,
                'base_price' => 450000, 'sold_count' => 85, 'is_featured' => true,
                'en' => ['name' => 'Silk Saree - Traditional Red', 'slug' => 'silk-saree-traditional-red', 'description' => 'Pure silk saree with traditional motif, perfect for festive occasions.'],
                'asset' => 'fashion/products/saree-silk',
                'variants' => [['sku' => 'FASH-SAREE-SILK-RED', 'price' => 450000, 'attrs' => ['fabric_color' => 'Red'], 'qty' => '15.000']],
            ],
            [
                'model_number' => 'FASH-SALWAR-COT',
                'category_id' => $cat('Salwar Kameez')?->id,
                'brand_id' => $brandAarong?->id,
                'base_price' => 320000, 'sold_count' => 92, 'is_featured' => true,
                'en' => ['name' => 'Cotton Salwar Kameez - Printed', 'slug' => 'cotton-salwar-kameez-printed', 'description' => 'Comfortable cotton salwar kameez with block print.'],
                'asset' => 'fashion/products/salwar-kameez-cotton',
                'variants' => [['sku' => 'FASH-SALWAR-COT-BLU', 'price' => 320000, 'attrs' => ['fabric_color' => 'Blue'], 'qty' => '18.000']],
            ],
            [
                'model_number' => 'FASH-KURTI-PRN',
                'category_id' => $cat('Kurti')?->id,
                'brand_id' => $brand('Yellow')?->id,
                'base_price' => 180000, 'sold_count' => 110, 'is_featured' => true,
                'en' => ['name' => 'Printed Kurti - Georgette', 'slug' => 'printed-kurti-georgette', 'description' => 'Trendy georgette kurti with floral print.'],
                'asset' => 'fashion/products/kurti-printed',
                'variants' => [['sku' => 'FASH-KURTI-PRN-M-PNK', 'price' => 180000, 'attrs' => ['size' => 'M', 'fabric_color' => 'Pink'], 'qty' => '20.000']],
            ],
            [
                'model_number' => 'FASH-TOPS-CAS',
                'category_id' => $cat('Tops')?->id,
                'brand_id' => $brand('Yellow')?->id,
                'base_price' => 95000, 'sold_count' => 65,
                'en' => ['name' => 'Casual Tops - White', 'slug' => 'casual-tops-white', 'description' => 'Casual tops for everyday wear.'],
                'asset' => 'fashion/products/tops-casual',
                'variants' => [['sku' => 'FASH-TOPS-CAS-M-WHT', 'price' => 95000, 'attrs' => ['size' => 'M', 'fabric_color' => 'White'], 'qty' => '25.000']],
            ],
            [
                'model_number' => 'FASH-DRESS-FLOR',
                'category_id' => $cat('Dresses')?->id ?? $cat('Dresses ')?->id,
                'brand_id' => $brandZara?->id,
                'base_price' => 280000, 'sold_count' => 78, 'is_featured' => true,
                'en' => ['name' => 'Floral Dress - Summer', 'slug' => 'floral-dress-summer', 'description' => 'Lightweight floral dress for summer.'],
                'asset' => 'fashion/products/dresses-floral',
                'variants' => [['sku' => 'FASH-DRESS-FLOR-M-RED', 'price' => 280000, 'attrs' => ['size' => 'M', 'fabric_color' => 'Red'], 'qty' => '12.000']],
            ],
            [
                'model_number' => 'FASH-HIJAB-PREM',
                'category_id' => $cat('Hijab & Modest Wear')?->id,
                'brand_id' => $brandZara?->id,
                'base_price' => 85000, 'sold_count' => 45,
                'en' => ['name' => 'Premium Hijab - Beige', 'slug' => 'premium-hijab-beige', 'description' => 'Premium georgette hijab, soft and breathable.'],
                'asset' => 'fashion/products/hijab-premium',
                'variants' => [['sku' => 'FASH-HIJAB-PREM-BEG', 'price' => 85000, 'attrs' => ['fabric_color' => 'Beige'], 'qty' => '30.000']],
            ],
            [
                'model_number' => 'FASH-PANJABI-EMB',
                'category_id' => $cat('Panjabi')?->id,
                'brand_id' => $brandAarong?->id,
                'base_price' => 350000, 'sold_count' => 95, 'is_featured' => true,
                'en' => ['name' => 'Embroidered Panjabi - White', 'slug' => 'embroidered-panjabi-white', 'description' => 'Classic white panjabi with embroidery for Eid.'],
                'asset' => 'fashion/products/panjabi-embroidered',
                'variants' => [['sku' => 'FASH-PANJABI-EMB-L-WHT', 'price' => 350000, 'attrs' => ['size' => 'L', 'fabric_color' => 'White'], 'qty' => '14.000']],
            ],
            [
                'model_number' => 'FASH-SHIRT-FORM',
                'category_id' => $cat('Shirts ') && $cat('Shirts ')->parent_id ? $cat('Shirts ')?->id : $cat('Shirts')?->id,
                'brand_id' => $brandZara?->id,
                'base_price' => 220000, 'sold_count' => 70,
                'en' => ['name' => 'Formal Shirt - Blue', 'slug' => 'formal-shirt-blue', 'description' => 'Slim fit formal shirt for office.'],
                'asset' => 'fashion/products/shirts-formal',
                'variants' => [['sku' => 'FASH-SHIRT-FORM-M-BLU', 'price' => 220000, 'attrs' => ['size' => 'M', 'fabric_color' => 'Blue'], 'qty' => '16.000']],
            ],
            [
                'model_number' => 'FASH-POLO-MEN',
                'category_id' => $cat('Polo Shirts')?->id,
                'brand_id' => $brand('Sailor')?->id,
                'base_price' => 150000, 'sold_count' => 55,
                'en' => ['name' => 'Polo Shirt - Navy', 'slug' => 'polo-shirt-navy', 'description' => 'Classic polo shirt, pique cotton.'],
                'asset' => 'fashion/products/polo-shirts-men',
                'variants' => [['sku' => 'FASH-POLO-MEN-L-NVY', 'price' => 150000, 'attrs' => ['size' => 'L', 'fabric_color' => 'Navy'], 'qty' => '18.000']],
            ],
            [
                'model_number' => 'FASH-JEANS-MEN',
                'category_id' => $cat('Jeans & Pants ') && $cat('Jeans & Pants ')->parent_id ? $cat('Jeans & Pants ')?->id : $cat('Jeans & Pants')?->id,
                'brand_id' => $brand('Sailor')?->id,
                'base_price' => 280000, 'sold_count' => 88, 'is_featured' => true,
                'en' => ['name' => "Men's Slim Jeans", 'slug' => 'mens-slim-jeans', 'description' => 'Slim fit denim jeans, stretchable.'],
                'asset' => 'fashion/products/jeans-men',
                'variants' => [['sku' => 'FASH-JEANS-MEN-32-BLU', 'price' => 280000, 'attrs' => ['size' => 'L', 'fabric_color' => 'Blue'], 'qty' => '13.000']],
            ],
            [
                'model_number' => 'FASH-HOODIES-MEN',
                'category_id' => $cat('Hoodies & Sweatshirts')?->id,
                'brand_id' => $brandNike?->id,
                'base_price' => 320000, 'sold_count' => 42,
                'en' => ['name' => 'Hoodie - Winter Collection', 'slug' => 'hoodie-winter-collection', 'description' => 'Warm fleece hoodie for winter.'],
                'asset' => 'fashion/products/hoodies-men',
                'variants' => [['sku' => 'FASH-HOODIES-MEN-L-BLK', 'price' => 320000, 'attrs' => ['size' => 'L', 'fabric_color' => 'Black'], 'qty' => '10.000']],
            ],
            [
                'model_number' => 'FASH-BOYS-SET',
                'category_id' => $cat("Boys' Clothing")?->id,
                'brand_id' => $brand('Infinity')?->id,
                'base_price' => 180000, 'sold_count' => 35,
                'en' => ['name' => "Boys' Clothing Set", 'slug' => 'boys-clothing-set', 'description' => 'Trendy boys outfit set.'],
                'asset' => 'fashion/products/boys-clothing-set',
                'variants' => [['sku' => 'FASH-BOYS-SET-M-BLU', 'price' => 180000, 'attrs' => ['size' => 'M', 'fabric_color' => 'Blue'], 'qty' => '12.000']],
            ],
            [
                'model_number' => 'FASH-GIRLS-FROCK',
                'category_id' => $cat("Girls' Clothing")?->id,
                'brand_id' => $brand('Infinity')?->id,
                'base_price' => 195000, 'sold_count' => 40,
                'en' => ['name' => "Girls' Frock - Pink", 'slug' => 'girls-frock-pink', 'description' => 'Cute frock for girls, party wear.'],
                'asset' => 'fashion/products/girls-clothing-frock',
                'variants' => [['sku' => 'FASH-GIRLS-FROCK-S-PNK', 'price' => 195000, 'attrs' => ['size' => 'S', 'fabric_color' => 'Pink'], 'qty' => '10.000']],
            ],
            [
                'model_number' => 'FASH-WATCH-LUX',
                'category_id' => $cat('Watches')?->id,
                'brand_id' => $brandNike?->id,
                'base_price' => 850000, 'sold_count' => 28, 'is_featured' => true,
                'en' => ['name' => 'Luxury Watch - Black', 'slug' => 'luxury-watch-black', 'description' => 'Elegant wrist watch for men.'],
                'asset' => 'fashion/products/watches-luxury',
                'variants' => [['sku' => 'FASH-WATCH-LUX-BLK', 'price' => 850000, 'attrs' => ['fabric_color' => 'Black'], 'qty' => '8.000']],
            ],
            [
                'model_number' => 'FASH-JEWELRY-GOLD',
                'category_id' => $cat('Jewelry')?->id,
                'brand_id' => $brand('Aarong')?->id,
                'base_price' => 1200000, 'sold_count' => 18,
                'en' => ['name' => 'Gold Plated Jewelry Set', 'slug' => 'gold-plated-jewelry-set', 'description' => 'Traditional jewelry set for weddings.'],
                'asset' => 'fashion/products/jewelry-gold',
                'variants' => [['sku' => 'FASH-JEWELRY-GOLD-RED', 'price' => 1200000, 'attrs' => ['fabric_color' => 'Red'], 'qty' => '5.000']],
            ],
        ];

        $legacy = [
            [
                'model_number' => 'FASH-SHIRT-001',
                'category_id' => $cat("Men's Fashion")?->id,
                'brand_id' => $brand('Zara')?->id,
                'base_price' => 280000, 'is_featured' => true, 'sold_count' => 42,
                'en' => ['name' => "Men's Oxford Cotton Shirt", 'slug' => 'mens-oxford-cotton-shirt', 'description' => 'Premium cotton Oxford shirt for office and casual.'],
                'asset' => 'fashion/product-1',
                'variants' => [['sku' => 'FASH-SHIRT-001-M-BLK', 'price' => 280000, 'attrs' => ['size' => 'M', 'fabric_color' => 'Black'], 'qty' => '12.000']],
            ],
            [
                'model_number' => 'FASH-DRESS-002',
                'category_id' => $cat("Women's Fashion")?->id,
                'brand_id' => $brand('Zara')?->id,
                'base_price' => 350000, 'is_featured' => true, 'sold_count' => 38,
                'en' => ['name' => "Women's Floral Summer Dress", 'slug' => 'womens-floral-summer-dress', 'description' => 'Lightweight floral dress perfect for summer outings.'],
                'asset' => 'fashion/product-2',
                'variants' => [['sku' => 'FASH-DRESS-002-M-RED', 'price' => 350000, 'attrs' => ['size' => 'M', 'fabric_color' => 'Red'], 'qty' => '8.000']],
            ],
            [
                'model_number' => 'FASH-SHOE-003',
                'category_id' => $cat('Accessories')?->id,
                'brand_id' => $brand('Nike')?->id,
                'base_price' => 420000, 'is_featured' => true, 'sold_count' => 25,
                'en' => ['name' => 'Unisex Runner Sneakers', 'slug' => 'unisex-runner-sneakers', 'description' => 'Lightweight runner with breathable mesh and cushioned sole.'],
                'asset' => 'fashion/product-3',
                'variants' => [['sku' => 'FASH-SHOE-003-42-BLK', 'price' => 420000, 'attrs' => ['size' => 'L', 'fabric_color' => 'Black'], 'qty' => '6.000']],
            ],
        ];

        $all = array_merge($definitions, $legacy);
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'fashion-hot-deals')->first();
        $hotSkus = ['FASH-KURTI-PRN-M-PNK', 'FASH-SAREE-SILK-RED', 'FASH-PANJABI-EMB-L-WHT', 'FASH-WATCH-LUX-BLK'];

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
            ], ['en' => $def['en']], $asset);

            $patch = [];
            if (isset($def['sold_count']) && (int) ($product->sold_count ?? 0) === 0) {
                $patch['sold_count'] = $def['sold_count'];
            }
            if (isset($def['is_featured']) && ! $product->is_featured && $def['is_featured']) {
                $patch['is_featured'] = true;
            }
            if ($patch !== []) {
                $product->update($patch);
            }

            foreach ($def['variants'] as $vDef) {
                $variant = $this->firstOrCreateVariant($tenant, $product, $vDef['sku'], (int) $vDef['price'], $asset);
                $compareAt = $vDef['compare_at_price'] ?? $def['compare_at_price'] ?? null;
                if ($compareAt !== null && $compareAt > (int) $variant->price) {
                    ProductVariant::query()->where('id', $variant->id)->update(['compare_at_price' => (int) $compareAt]);
                }
                if (in_array($vDef['sku'], $hotSkus, true) && $variant->compare_at_price === null) {
                    $inflated = (int) round((int) $variant->price * 1.35);
                    ProductVariant::query()->where('id', $variant->id)->update(['compare_at_price' => $inflated]);
                }
                $this->attachVariantAttributes($tenant, $variant, $vDef['attrs'] ?? []);
                $this->ensureStock($tenant, $variant, (string) ($vDef['qty'] ?? '10.000'));
            }

            if ($campaign && in_array($def['variants'][0]['sku'] ?? '', $hotSkus, true)) {
                $exists = DB::table('campaign_product')->where('campaign_id', $campaign->id)->where('product_id', $product->id)->exists();
                if (! $exists) {
                    $max = DB::table('campaign_product')->where('campaign_id', $campaign->id)->max('sort_order');
                    DB::table('campaign_product')->insert(['campaign_id' => $campaign->id, 'product_id' => $product->id, 'sort_order' => (int) $max + 1]);
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
            $item = MenuItem::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'menu_id' => $menu->id, 'label' => $label, 'parent_id' => $parentId],
                ['link_type' => $linkType, 'link_value' => $linkValue, 'visibility' => Visibility::All, 'sort_order' => $sort, 'badge_text' => $badge]
            );
            $currentType = $item->link_type instanceof LinkType ? $item->link_type->value : (string) $item->link_type;
            if ($currentType !== $linkType || $item->link_value !== $linkValue) {
                $item->update(['link_type' => $linkType, 'link_value' => $linkValue]);
            }

            return $item;
        };

        $home = $mk('Home', LinkType::External->value, '/', 10);
        $women = $mk('Women', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Women') ?? '/', 20);
        $men = $mk('Men', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Men') ?? '/', 30);
        $kids = $mk('Kids', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Kids') ?? '/', 40);
        $accessories = $mk('Accessories', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Accessories') ?? '/', 50);
        $brands = $mk('Brands', LinkType::External->value, '/brands', 60);
        $mk('Blog', LinkType::BlogIndex->value, null, 65);
        $mk('Sale 🔥', LinkType::External->value, '/offers', 70, null, '🔥');

        // Women dropdown
        $mk("Women's Clothing", LinkType::Category->value, $this->categorySlugForMenu($tenant, "Women's Clothing") ?? '/', 21, $women->id);
        $mk('Saree', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Saree') ?? '/', 22, $women->id);
        $mk('Salwar Kameez', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Salwar Kameez') ?? '/', 23, $women->id);
        $mk('Kurti', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Kurti') ?? '/', 24, $women->id);
        $mk("Women's Footwear", LinkType::Category->value, $this->categorySlugForMenu($tenant, "Women's Footwear") ?? '/', 25, $women->id);

        // Men dropdown
        $mk("Men's Clothing", LinkType::Category->value, $this->categorySlugForMenu($tenant, "Men's Clothing") ?? '/', 31, $men->id);
        $mk('Panjabi', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Panjabi') ?? '/', 32, $men->id);
        $mk('Shirts', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Shirts ') ?? $this->categorySlugForMenu($tenant, 'Shirts') ?? '/', 33, $men->id);
        $mk('T-Shirts', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'T-Shirts ') ?? '/', 34, $men->id);

        // Kids dropdown
        $mk("Boys' Clothing", LinkType::Category->value, $this->categorySlugForMenu($tenant, "Boys' Clothing") ?? '/', 41, $kids->id);
        $mk("Girls' Clothing", LinkType::Category->value, $this->categorySlugForMenu($tenant, "Girls' Clothing") ?? '/', 42, $kids->id);

        // Accessories dropdown
        $mk('Watches', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Watches') ?? '/', 51, $accessories->id);
        $mk('Sunglasses', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Sunglasses') ?? '/', 52, $accessories->id);
        $mk('Jewelry', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Jewelry') ?? '/', 53, $accessories->id);

        // Brands
        foreach (['Zara', 'Nike', 'Aarong', 'Yellow', 'Infinity'] as $idx => $bName) {
            $mk($bName, LinkType::Brand->value, Str::slug($bName), 61 + $idx, $brands->id);
        }

        // Special navigation (non-category)
        $mk('New Arrivals', LinkType::External->value, '/products?sort=latest', 71);
        $mk('Best Sellers', LinkType::External->value, '/products?sort=best_selling', 72);
        $mk('Trending', LinkType::External->value, '/products?sort=trending', 73);
        $mk('Winter Collection', LinkType::External->value, '/collections/winter-collection', 74);
        $mk('Eid Collection', LinkType::External->value, '/collections/eid-collection', 75);
    }

    private function categorySlugForMenu(Tenant $tenant, string $name): ?string
    {
        $cat = Category::query()->where('tenant_id', $tenant->id)->where('name', $name)->first();

        return $cat?->slug;
    }

    private function seedSupportDefaults(Tenant $tenant): void
    {
        try {
            app(TrustContentSeeder::class)->run($tenant);
        } catch (\Throwable $e) {
        }

        PaymentMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'cod'],
            ['name' => 'Cash on Delivery', 'display_name' => 'Cash on Delivery', 'type' => PaymentMethodType::Cod, 'is_active' => true, 'sort_order' => 1, 'gateway_ownership' => 'shop']
        );
        PaymentMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'bkash_manual'],
            ['name' => 'bKash', 'display_name' => 'bKash Send Money', 'type' => PaymentMethodType::ManualMfs, 'provider' => 'bKash', 'account_number' => '01XXXXXXXXX', 'account_name' => 'Merchant bKash', 'instructions' => 'Send money to the number above and submit your Transaction ID.', 'requires_verification' => true, 'is_active' => true, 'sort_order' => 2, 'gateway_ownership' => 'shop']
        );
        PaymentMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'nagad_manual'],
            ['name' => 'Nagad', 'display_name' => 'Nagad Send Money', 'type' => PaymentMethodType::ManualMfs, 'provider' => 'Nagad', 'account_number' => '01XXXXXXXXX', 'account_name' => 'Merchant Nagad', 'instructions' => 'Send money to Nagad number and submit Transaction ID.', 'requires_verification' => true, 'is_active' => true, 'sort_order' => 3, 'gateway_ownership' => 'shop']
        );

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

        Outlet::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'main-outlet-gulshan-fashion'],
            [
                'name' => 'Main Outlet - Gulshan (Fashion)',
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
    }

    private function firstOrCreateCategory(Tenant $tenant, string $name, ?int $parentId = null, ?string $slug = null, ?string $imageStem = null): Category
    {
        $slug = $slug ?? Str::slug($name);
        if ($slug === '' || $slug === null) {
            $slug = 'cat-'.substr(md5($name.$parentId), 0, 8);
        }
        $category = Category::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name, 'parent_id' => $parentId],
            ['slug' => $slug]
        );
        if ($category->slug === '' || $category->slug !== $slug) {
            if ($category->slug === '' || $category->slug === Str::slug($name)) {
                $exists = Category::query()->where('tenant_id', $tenant->id)->where('slug', $slug)->where('id', '!=', $category->id)->exists();
                if (! $exists) {
                    $category->update(['slug' => $slug]);
                }
            }
        }
        if ($imageStem !== null) {
            $this->ensureCategoryImage($category, $imageStem);
        }

        return $category->fresh() ?? $category;
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
            return;
        }
        $src = $this->resolveAssetPath($stem);
        if ($src === null) {
            $src = $this->resolveAssetPath('fashion/categories/women');
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
        $filename = Str::slug($category->slug).'-'.$category->id.'.'.$ext;
        $dest = $dir.'/'.$filename;
        if ($hasImage && $category->image_path !== 'category-images/'.$filename && is_file($currentDest) && ($currentIsPlaceholder || $force)) {
            @unlink($currentDest);
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
        return Brand::query()->firstOrCreate(['tenant_id' => $tenant->id, 'slug' => Str::slug($name)], ['name' => $name]);
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

    private function firstOrCreateAttribute(Tenant $tenant, array $attributes, array $options): AttributeDefinition
    {
        $def = AttributeDefinition::query()->firstOrCreate(['tenant_id' => $tenant->id, 'code' => $attributes['code']], ['label' => $attributes['label'], 'data_type' => $attributes['data_type'], 'unit' => $attributes['unit'], 'group' => $attributes['group'], 'is_filterable' => $attributes['is_filterable'], 'is_variant_defining' => $attributes['is_variant_defining'], 'sort_order' => $attributes['sort_order'], 'is_global' => false]);
        foreach ($options as $idx => $value) {
            AttributeOption::query()->firstOrCreate(['tenant_id' => $tenant->id, 'attribute_definition_id' => $def->id, 'value' => $value], ['label' => $value, 'sort_order' => $idx + 1]);
        }

        return $def;
    }

    private function firstOrCreateHomepageSection(Tenant $tenant, string $type, string $title, int $sortOrder, array $config = []): HomepageSection
    {
        $section = HomepageSection::query()->firstOrCreate(['tenant_id' => $tenant->id, 'type' => $type, 'title' => $title], ['config' => $config, 'visibility' => Visibility::All, 'is_active' => true, 'sort_order' => $sortOrder]);
        $needsUpdate = false;
        $updates = [];
        if (($section->config ?? []) !== $config && $config !== []) {
            $existingConfig = $section->config ?? [];
            if ($existingConfig === [] || $existingConfig === null || (! isset($existingConfig['category_ids']) && isset($config['category_ids']))) {
                $updates['config'] = array_merge((array) $existingConfig, $config);
                $needsUpdate = true;
            }
        }
        if ((int) $section->sort_order !== $sortOrder) {
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
        $banner = Banner::query()->firstOrCreate(['tenant_id' => $tenant->id, 'title' => $title, 'placement' => $placement], ['media_type' => 'image', 'visibility' => Visibility::All, 'link_type' => 'none', 'is_active' => true, 'sort_order' => $sortOrder]);
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

    private function firstOrCreateProduct(Tenant $tenant, array $attrs, array $translations, ?string $assetStem = null): Product
    {
        $product = Product::query()->firstOrCreate(['tenant_id' => $tenant->id, 'model_number' => $attrs['model_number']], array_merge(['status' => ProductStatus::Published, 'type' => 'simple', 'published_at' => now()], $attrs));
        foreach ($translations as $locale => $data) {
            ProductTranslation::query()->firstOrCreate(['product_id' => $product->id, 'locale' => $locale], ['tenant_id' => $tenant->id, 'name' => $data['name'], 'slug' => $data['slug'] ?? Str::slug($data['name']), 'description' => $data['description'] ?? null]);
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
        $variant = ProductVariant::query()->firstOrCreate(['tenant_id' => $tenant->id, 'sku' => $sku], ['product_id' => $product->id, 'price' => $price, 'is_active' => true]);
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
            ProductAttributeValue::query()->firstOrCreate(['product_variant_id' => $variant->id, 'attribute_definition_id' => $def->id], ['tenant_id' => $tenant->id, 'product_id' => $variant->product_id, 'attribute_option_id' => $opt->id]);
        }
    }

    private function ensureStock(Tenant $tenant, ProductVariant $variant, string $qty): void
    {
        $loc = Location::query()->where('tenant_id', $tenant->id)->where('is_default', true)->first() ?? Location::query()->where('tenant_id', $tenant->id)->first();
        if (! $loc) {
            return;
        }
        $stock = StockItem::query()->firstOrCreate(['product_variant_id' => $variant->id, 'location_id' => $loc->id], ['tenant_id' => $tenant->id, 'quantity' => $qty, 'reserved_quantity' => '0.000']);
        if (bccomp((string) $stock->quantity, '0.000', 3) === 0 && bccomp($qty, '0.000', 3) !== 0) {
            $stock->update(['quantity' => $qty]);
        }
    }

    private function resolveAssetPath(string $stem): ?string
    {
        $stem = ltrim($stem, '/');
        $exact = base_path('resources/starter-assets/'.$stem);
        if (is_file($exact)) {
            return $exact;
        }

        if (! str_contains($stem, '/')) {
            $prefixes = [
                'fashion/categories/'.$stem,
                'fashion/products/'.$stem,
                'fashion/brands/'.$stem,
                'fashion/banners/'.$stem,
                'electronics/categories/'.$stem,
                'electronics/products/'.$stem,
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
                    if (strcasecmp($candidateBase, $baseWithoutExt) === 0 || strcasecmp($candidateBase, $base) === 0) {
                        $candidates[] = $candidatePath;

                        continue;
                    }
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
