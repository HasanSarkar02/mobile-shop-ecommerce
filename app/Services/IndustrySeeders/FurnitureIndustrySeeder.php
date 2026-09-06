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

final class FurnitureIndustrySeeder
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
        $preset = ThemePresets::forIndustry('furniture');
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
        $livingRoom = $this->firstOrCreateCategory($tenant, 'Living Room', null, 'living-room', 'furniture/categories/living-room');
        $bedroom = $this->firstOrCreateCategory($tenant, 'Bedroom', null, 'bedroom', 'furniture/categories/bedroom');
        $officeLegacy = $this->firstOrCreateCategory($tenant, 'Office', null, 'office', 'furniture/categories/office-furniture');

        // Living Room children
        $this->firstOrCreateCategory($tenant, 'Sofas', $livingRoom->id, 'sofas', 'furniture/categories/sofas');
        $this->firstOrCreateCategory($tenant, 'Sofa Sets', $livingRoom->id, 'sofa-sets', 'furniture/categories/sofa-sets');
        $this->firstOrCreateCategory($tenant, 'Sectional Sofas', $livingRoom->id, 'sectional-sofas', 'furniture/categories/sectional-sofas');
        $this->firstOrCreateCategory($tenant, 'Armchairs & Recliners', $livingRoom->id, 'armchairs-recliners', 'furniture/categories/armchairs-recliners');
        $this->firstOrCreateCategory($tenant, 'Coffee Tables', $livingRoom->id, 'coffee-tables', 'furniture/categories/coffee-tables');
        $this->firstOrCreateCategory($tenant, 'TV Units', $livingRoom->id, 'tv-units', 'furniture/categories/tv-units');
        $this->firstOrCreateCategory($tenant, 'Side Tables', $livingRoom->id, 'side-tables', 'furniture/categories/side-tables');
        $this->firstOrCreateCategory($tenant, 'Display Cabinets', $livingRoom->id, 'display-cabinets', 'furniture/categories/display-cabinets');

        // Bedroom children
        $this->firstOrCreateCategory($tenant, 'Beds', $bedroom->id, 'beds', 'furniture/categories/beds');
        $this->firstOrCreateCategory($tenant, 'Bedside Tables', $bedroom->id, 'bedside-tables', 'furniture/categories/bedside-tables');
        $this->firstOrCreateCategory($tenant, 'Wardrobes', $bedroom->id, 'wardrobes', 'furniture/categories/wardrobes');
        $this->firstOrCreateCategory($tenant, 'Dressers', $bedroom->id, 'dressers', 'furniture/categories/dressers');
        $this->firstOrCreateCategory($tenant, 'Dressing Tables', $bedroom->id, 'dressing-tables', 'furniture/categories/dressing-tables');
        $this->firstOrCreateCategory($tenant, 'Bedroom Sets', $bedroom->id, 'bedroom-sets', 'furniture/categories/bedroom-sets');

        // Dining Room (new top)
        $diningRoom = $this->firstOrCreateCategory($tenant, 'Dining Room', null, 'dining-room', 'furniture/categories/dining-room');
        $this->firstOrCreateCategory($tenant, 'Dining Tables', $diningRoom->id, 'dining-tables', 'furniture/categories/dining-tables');
        $this->firstOrCreateCategory($tenant, 'Dining Chairs', $diningRoom->id, 'dining-chairs', 'furniture/categories/dining-chairs');
        $this->firstOrCreateCategory($tenant, 'Dining Sets', $diningRoom->id, 'dining-sets', 'furniture/categories/dining-sets');
        $this->firstOrCreateCategory($tenant, 'Benches', $diningRoom->id, 'benches', 'furniture/categories/benches');
        $this->firstOrCreateCategory($tenant, 'Dining Cabinets', $diningRoom->id, 'dining-cabinets', 'furniture/categories/dining-cabinets');

        // Office Furniture (reuse legacy Office as parent)
        $office = $officeLegacy;
        // Ensure Office has correct slug for new spec (office-furniture) but keep legacy slug 'office' for test compat - keep as is, add children
        $this->firstOrCreateCategory($tenant, 'Office Desks', $office->id, 'office-desks', 'furniture/categories/office-desks');
        $this->firstOrCreateCategory($tenant, 'Office Chairs', $office->id, 'office-chairs', 'furniture/categories/office-chairs');
        $this->firstOrCreateCategory($tenant, 'Executive Tables', $office->id, 'executive-tables', 'furniture/categories/executive-tables');
        $this->firstOrCreateCategory($tenant, 'Filing Cabinets', $office->id, 'filing-cabinets', 'furniture/categories/filing-cabinets');
        $this->firstOrCreateCategory($tenant, 'Bookcases', $office->id, 'bookcases-office', 'furniture/categories/bookcases');
        $this->firstOrCreateCategory($tenant, 'Office Storage', $office->id, 'office-storage', 'furniture/categories/office-storage');

        // Study & Workspace
        $study = $this->firstOrCreateCategory($tenant, 'Study & Workspace', null, 'study-workspace', 'furniture/categories/study-workspace');
        $this->firstOrCreateCategory($tenant, 'Study Tables', $study->id, 'study-tables', 'furniture/categories/study-tables');
        $this->firstOrCreateCategory($tenant, 'Study Chairs', $study->id, 'study-chairs', 'furniture/categories/study-chairs');
        $this->firstOrCreateCategory($tenant, 'Computer Desks', $study->id, 'computer-desks', 'furniture/categories/computer-desks');
        $this->firstOrCreateCategory($tenant, 'Bookshelves', $study->id, 'bookshelves', 'furniture/categories/bookshelves');
        $this->firstOrCreateCategory($tenant, 'Storage Cabinets', $study->id, 'storage-cabinets', 'furniture/categories/storage-cabinets');

        // Outdoor Furniture
        $outdoor = $this->firstOrCreateCategory($tenant, 'Outdoor Furniture', null, 'outdoor-furniture', 'furniture/categories/outdoor-furniture');
        $this->firstOrCreateCategory($tenant, 'Outdoor Chairs', $outdoor->id, 'outdoor-chairs', 'furniture/categories/outdoor-chairs');
        $this->firstOrCreateCategory($tenant, 'Outdoor Tables', $outdoor->id, 'outdoor-tables', 'furniture/categories/outdoor-tables');
        $this->firstOrCreateCategory($tenant, 'Garden Sets', $outdoor->id, 'garden-sets', 'furniture/categories/garden-sets');
        $this->firstOrCreateCategory($tenant, 'Swing Chairs', $outdoor->id, 'swing-chairs', 'furniture/categories/swing-chairs');
        $this->firstOrCreateCategory($tenant, 'Outdoor Sofas', $outdoor->id, 'outdoor-sofas', 'furniture/categories/outdoor-sofas');

        // Storage & Organization
        $storage = $this->firstOrCreateCategory($tenant, 'Storage & Organization', null, 'storage-organization', 'furniture/categories/storage-organization');
        $this->firstOrCreateCategory($tenant, 'Cabinets', $storage->id, 'cabinets', 'furniture/categories/cabinets');
        $this->firstOrCreateCategory($tenant, 'Shelves', $storage->id, 'shelves', 'furniture/categories/shelves');
        $this->firstOrCreateCategory($tenant, 'Bookcases ', $storage->id, 'bookcases-storage', 'furniture/categories/bookcases-storage');
        $this->firstOrCreateCategory($tenant, 'Shoe Racks', $storage->id, 'shoe-racks', 'furniture/categories/shoe-racks');
        $this->firstOrCreateCategory($tenant, 'Storage Units', $storage->id, 'storage-units', 'furniture/categories/storage-units');

        // Kids Furniture
        $kids = $this->firstOrCreateCategory($tenant, 'Kids Furniture', null, 'kids-furniture', 'furniture/categories/kids-furniture');
        $this->firstOrCreateCategory($tenant, 'Kids Beds', $kids->id, 'kids-beds', 'furniture/categories/kids-beds');
        $this->firstOrCreateCategory($tenant, 'Kids Tables', $kids->id, 'kids-tables', 'furniture/categories/kids-tables');
        $this->firstOrCreateCategory($tenant, 'Kids Chairs', $kids->id, 'kids-chairs', 'furniture/categories/kids-chairs');
        $this->firstOrCreateCategory($tenant, 'Kids Storage', $kids->id, 'kids-storage', 'furniture/categories/kids-storage');

        // Furniture Accessories
        $acc = $this->firstOrCreateCategory($tenant, 'Furniture Accessories', null, 'furniture-accessories', 'furniture/categories/furniture-accessories');
        $this->firstOrCreateCategory($tenant, 'Cushions', $acc->id, 'cushions', 'furniture/categories/cushions');
        $this->firstOrCreateCategory($tenant, 'Chair Covers', $acc->id, 'chair-covers', 'furniture/categories/chair-covers');
        $this->firstOrCreateCategory($tenant, 'Mattress', $acc->id, 'mattress', 'furniture/categories/mattress');
        $this->firstOrCreateCategory($tenant, 'Furniture Care', $acc->id, 'furniture-care', 'furniture/categories/furniture-care');
    }

    private function seedBrands(Tenant $tenant): void
    {
        $brands = [
            'WoodCraft' => 'furniture/brands/woodcraft',
            'Comfort Living' => 'furniture/brands/comfort-living',
            'Hatil' => 'furniture/brands/hatil',
            'Navana' => 'furniture/brands/navana',
            'Otobi' => 'furniture/brands/otobi',
        ];
        foreach ($brands as $name => $stem) {
            $brand = $this->firstOrCreateBrand($tenant, $name);
            $this->ensureBrandLogo($brand, $stem);
        }
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, ['code' => 'material', 'label' => 'Material', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 1], ['Wood', 'Metal', 'Fabric', 'Leather', 'MDF', 'Particle Board']);
        $this->firstOrCreateAttribute($tenant, ['code' => 'dimensions', 'label' => 'Dimensions', 'data_type' => AttributeDataType::Text, 'unit' => 'cm', 'group' => 'Specifications', 'is_filterable' => false, 'is_variant_defining' => true, 'sort_order' => 2], []);
        $this->firstOrCreateAttribute($tenant, ['code' => 'upholstery_color', 'label' => 'Upholstery Color', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Appearance', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 3], ['Beige', 'Gray', 'Brown', 'Navy', 'White', 'Black', 'Walnut']);
    }

    private function seedCampaigns(Tenant $tenant): void
    {
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'furniture-hot-deals'],
            [
                'name' => 'Furniture Hot Deals',
                'description' => 'Up to 35% OFF on sofas, beds and dining sets!',
                'accent_color' => '#92400e',
                'short_tagline' => 'Up to 35% OFF',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(7),
            ]
        );
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'new-arrivals-furniture'],
            [
                'name' => 'New Arrivals',
                'description' => 'New collection - modern sofas, beds and office furniture',
                'accent_color' => '#78350f',
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
        $mainIds = Category::query()->where('tenant_id', $tenant->id)->whereNull('parent_id')->whereIn('slug', ['living-room', 'bedroom', 'dining-room', 'office', 'study-workspace', 'outdoor-furniture', 'storage-organization', 'kids-furniture', 'furniture-accessories'])->orderBy('id')->pluck('id')->all();
        $catConfig = ['source' => 'category', 'limit' => 9];
        if ($mainIds !== []) {
            $catConfig['category_ids'] = $mainIds;
        }
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Browse by Room', 30, $catConfig);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Featured Furniture', 40, ['data_source' => 'featured', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Brand', 50, ['source' => 'brand', 'limit' => 5]);
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'furniture-hot-deals')->first();
        $hot = $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Hot Deals - Furniture Sale', 60, ['data_source' => 'campaign', 'limit' => 8]);
        if ($campaign && $hot->campaign_id !== $campaign->id) {
            $hot->update(['campaign_id' => $campaign->id]);
        }
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'New Arrivals', 70, ['data_source' => 'latest', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Best Selling Products', 80, ['data_source' => 'best_selling', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Promotional Banner', 90, ['placement' => 'promo']);
        $this->firstOrCreateHomepageSection($tenant, 'blog_grid', 'From the Journal', 100, ['limit' => 3]);
        $this->firstOrCreateHomepageSection($tenant, 'newsletter_cta', 'Stay Updated', 110);
    }

    private function seedBanners(Tenant $tenant): void
    {
        $this->firstOrCreateBanner($tenant, 'Hero Banner 1', 'hero', 'furniture/banners/hero-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Hero Banner 2', 'hero', 'furniture/banners/hero-banner-2', 1);
        $this->firstOrCreateBanner($tenant, 'Promotional Banner 1', 'promo', 'furniture/banners/promo-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Flash Sale Banner', 'promo', 'furniture/banners/flash-sale-banner', 1);
        $this->firstOrCreateBanner($tenant, 'Furniture Hero', 'hero', 'furniture/hero', 10);
    }

    private function seedProducts(Tenant $tenant): void
    {
        $cat = fn (string $name): ?Category => Category::query()->where('tenant_id', $tenant->id)->where('name', $name)->first();
        $brand = fn (string $name): ?Brand => Brand::query()->where('tenant_id', $tenant->id)->where('slug', Str::slug($name))->first();
        $brandWood = $brand('WoodCraft') ?? $brand('Hatil');
        $brandComfort = $brand('Comfort Living') ?? $brandWood;

        $definitions = [
            [
                'model_number' => 'FURN-SOFA-3FAB',
                'category_id' => $cat('Sofas')?->id ?? $cat('Living Room')?->id,
                'brand_id' => $brandComfort?->id,
                'base_price' => 4200000, 'sold_count' => 42, 'is_featured' => true,
                'en' => ['name' => 'Fabric 3-Seater Sofa - Beige', 'slug' => 'fabric-3-seater-sofa-beige', 'description' => 'Comfortable 3-seater fabric sofa with solid wood legs, perfect for living room.'],
                'asset' => 'furniture/products/sofa-3seater-fabric',
                'variants' => [['sku' => 'FURN-SOFA-3FAB-BEG', 'price' => 4200000, 'attrs' => ['material' => 'Fabric', 'upholstery_color' => 'Beige'], 'qty' => '4.000']],
            ],
            [
                'model_number' => 'FURN-SOFA-SET-5',
                'category_id' => $cat('Sofa Sets')?->id,
                'brand_id' => $brandComfort?->id,
                'base_price' => 8500000, 'sold_count' => 18, 'is_featured' => true,
                'en' => ['name' => '5-Piece Sofa Set with Coffee Table', 'slug' => '5-piece-sofa-set-with-coffee-table', 'description' => 'Complete sofa set with 3+1+1 and wooden coffee table.'],
                'asset' => 'furniture/products/sofa-set-5pieces',
                'variants' => [['sku' => 'FURN-SOFA-SET-5-BRN', 'price' => 8500000, 'attrs' => ['material' => 'Fabric', 'upholstery_color' => 'Brown'], 'qty' => '2.000']],
            ],
            [
                'model_number' => 'FURN-ARM-RECL',
                'category_id' => $cat('Armchairs & Recliners')?->id,
                'brand_id' => $brandComfort?->id,
                'base_price' => 3200000, 'sold_count' => 15,
                'en' => ['name' => 'Armchair Recliner - Gray', 'slug' => 'armchair-recliner-gray', 'description' => 'Recliner armchair with metal frame and fabric upholstery.'],
                'asset' => 'furniture/products/armchair-recliner',
                'variants' => [['sku' => 'FURN-ARM-RECL-GRY', 'price' => 3200000, 'attrs' => ['material' => 'Fabric', 'upholstery_color' => 'Gray'], 'qty' => '5.000']],
            ],
            [
                'model_number' => 'FURN-COFFEE-WOOD',
                'category_id' => $cat('Coffee Tables')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 1850000, 'sold_count' => 22, 'is_featured' => true,
                'en' => ['name' => 'Modern Wooden Coffee Table - Walnut', 'slug' => 'modern-wooden-coffee-table-walnut', 'description' => 'Solid wood coffee table with minimalist design and storage shelf.'],
                'asset' => 'furniture/products/coffee-table-wooden',
                'variants' => [['sku' => 'FURN-COFFEE-WOOD-WAL', 'price' => 1850000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Brown'], 'qty' => '6.000']],
            ],
            [
                'model_number' => 'FURN-BED-KING',
                'category_id' => $cat('Beds')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 6500000, 'sold_count' => 28, 'is_featured' => true,
                'en' => ['name' => 'King Size Bed with Headboard', 'slug' => 'king-size-bed-with-headboard', 'description' => 'Solid wood king size bed with cushioned headboard and storage.'],
                'asset' => 'furniture/products/bed-king-size',
                'variants' => [['sku' => 'FURN-BED-KING-WAL', 'price' => 6500000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Walnut'], 'qty' => '3.000']],
            ],
            [
                'model_number' => 'FURN-WARD-3D',
                'category_id' => $cat('Wardrobes')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 4800000, 'sold_count' => 12,
                'en' => ['name' => '3-Door Wardrobe - White', 'slug' => '3-door-wardrobe-white', 'description' => 'Spacious 3-door wardrobe with mirror and drawers.'],
                'asset' => 'furniture/products/bed-king-size',
                'variants' => [['sku' => 'FURN-WARD-3D-WHT', 'price' => 4800000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'White'], 'qty' => '2.000']],
            ],
            [
                'model_number' => 'FURN-DINING-6',
                'category_id' => $cat('Dining Tables')?->id ?? $cat('Dining Room')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 7200000, 'sold_count' => 16, 'is_featured' => true,
                'en' => ['name' => '6-Seater Dining Table Set', 'slug' => '6-seater-dining-table-set', 'description' => 'Wooden dining table with 6 chairs, perfect for family gatherings.'],
                'asset' => 'furniture/products/dining-table-6chairs',
                'variants' => [['sku' => 'FURN-DINING-6-WAL', 'price' => 7200000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Brown'], 'qty' => '2.000']],
            ],
            [
                'model_number' => 'FURN-OFF-DESK-EXE',
                'category_id' => $cat('Office Desks')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 3800000, 'sold_count' => 10,
                'en' => ['name' => 'Executive Office Desk', 'slug' => 'executive-office-desk', 'description' => 'L-shaped executive desk with drawers and cable management.'],
                'asset' => 'furniture/products/office-desk-executive',
                'variants' => [['sku' => 'FURN-OFF-DESK-EXE-WAL', 'price' => 3800000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Walnut'], 'qty' => '4.000']],
            ],
            [
                'model_number' => 'FURN-OFF-CHAIR-ERG',
                'category_id' => $cat('Office Chairs')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 1550000, 'sold_count' => 35, 'is_featured' => true,
                'en' => ['name' => 'Ergonomic Office Chair - Mesh', 'slug' => 'ergonomic-office-chair-mesh', 'description' => 'Mesh office chair with lumbar support and adjustable height.'],
                'asset' => 'furniture/products/office-chair-ergonomic',
                'variants' => [['sku' => 'FURN-OFF-CHAIR-ERG-GRY', 'price' => 1550000, 'attrs' => ['material' => 'Metal', 'upholstery_color' => 'Gray'], 'qty' => '8.000']],
            ],
            [
                'model_number' => 'FURN-STUDY-TBL',
                'category_id' => $cat('Study Tables')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 2200000, 'sold_count' => 18,
                'en' => ['name' => 'Study Table for Students', 'slug' => 'study-table-for-students', 'description' => 'Compact study table with bookshelf and drawer.'],
                'asset' => 'furniture/products/study-table-student',
                'variants' => [['sku' => 'FURN-STUDY-TBL-WAL', 'price' => 2200000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Brown'], 'qty' => '5.000']],
            ],
            [
                'model_number' => 'FURN-BOOK-5T',
                'category_id' => $cat('Bookshelves')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 2800000, 'sold_count' => 14,
                'en' => ['name' => '5-Tier Bookshelf - Wooden', 'slug' => '5-tier-bookshelf-wooden', 'description' => 'Tall 5-tier bookshelf for study and office storage.'],
                'asset' => 'furniture/products/bookshelf-5tier',
                'variants' => [['sku' => 'FURN-BOOK-5T-WAL', 'price' => 2800000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Walnut'], 'qty' => '4.000']],
            ],
            [
                'model_number' => 'FURN-SWING-OUT',
                'category_id' => $cat('Swing Chairs')?->id,
                'brand_id' => $brandComfort?->id,
                'base_price' => 3500000, 'sold_count' => 9,
                'en' => ['name' => 'Outdoor Swing Chair - Hanging', 'slug' => 'outdoor-swing-chair-hanging', 'description' => 'Hanging swing chair for garden and balcony.'],
                'asset' => 'furniture/products/outdoor-swing-chair',
                'variants' => [['sku' => 'FURN-SWING-OUT-BEG', 'price' => 3500000, 'attrs' => ['material' => 'Metal', 'upholstery_color' => 'Beige'], 'qty' => '3.000']],
            ],
            [
                'model_number' => 'FURN-SHOE-RACK',
                'category_id' => $cat('Shoe Racks')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 1800000, 'sold_count' => 20,
                'en' => ['name' => 'Wooden Shoe Rack - 3 Tier', 'slug' => 'wooden-shoe-rack-3-tier', 'description' => '3-tier shoe rack with doors, holds 12 pairs.'],
                'asset' => 'furniture/products/shoe-rack-wooden',
                'variants' => [['sku' => 'FURN-SHOE-RACK-WAL', 'price' => 1800000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Brown'], 'qty' => '6.000']],
            ],
            [
                'model_number' => 'FURN-KIDS-BUNK',
                'category_id' => $cat('Kids Beds')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 4200000, 'sold_count' => 8,
                'en' => ['name' => 'Kids Bunk Bed - Wooden', 'slug' => 'kids-bunk-bed-wooden', 'description' => 'Space-saving bunk bed for kids with ladder and storage.'],
                'asset' => 'furniture/products/kids-bed-bunk',
                'variants' => [['sku' => 'FURN-KIDS-BUNK-WHT', 'price' => 4200000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'White'], 'qty' => '2.000']],
            ],
            [
                'model_number' => 'FURN-CUSHION-SET',
                'category_id' => $cat('Cushions')?->id,
                'brand_id' => $brandComfort?->id,
                'base_price' => 350000, 'sold_count' => 45, 'is_featured' => true,
                'en' => ['name' => 'Cushion Cover Set - 5 Pieces', 'slug' => 'cushion-cover-set-5-pieces', 'description' => 'Set of 5 cushion covers with modern prints.'],
                'asset' => 'furniture/products/cushion-cover-set',
                'variants' => [['sku' => 'FURN-CUSHION-SET-BEG', 'price' => 350000, 'attrs' => ['material' => 'Fabric', 'upholstery_color' => 'Beige'], 'qty' => '15.000']],
            ],
        ];

        $legacy = [
            [
                'model_number' => 'FURN-TABLE-001',
                'category_id' => $cat('Living Room')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 1850000, 'is_featured' => true, 'sold_count' => 22,
                'en' => ['name' => 'Modern Wooden Coffee Table', 'slug' => 'modern-wooden-coffee-table', 'description' => 'Solid wood coffee table with minimalist design.'],
                'asset' => 'furniture/product-1',
                'variants' => [['sku' => 'FURN-TABLE-001-WALNUT', 'price' => 1850000, 'attrs' => ['material' => 'Wood', 'upholstery_color' => 'Brown'], 'qty' => '6.000']],
            ],
            [
                'model_number' => 'FURN-SOFA-002',
                'category_id' => $cat('Living Room')?->id,
                'brand_id' => $brandComfort?->id,
                'base_price' => 4200000, 'is_featured' => true, 'sold_count' => 18,
                'en' => ['name' => 'Fabric 3-Seater Sofa', 'slug' => 'fabric-3-seater-sofa', 'description' => 'Comfortable fabric sofa with wooden legs.'],
                'asset' => 'furniture/product-2',
                'variants' => [['sku' => 'FURN-SOFA-002-BEIGE', 'price' => 4200000, 'attrs' => ['material' => 'Fabric', 'upholstery_color' => 'Beige'], 'qty' => '3.000']],
            ],
            [
                'model_number' => 'FURN-CHAIR-003',
                'category_id' => $cat('Office')?->id,
                'brand_id' => $brandWood?->id,
                'base_price' => 1550000, 'is_featured' => true, 'sold_count' => 25,
                'en' => ['name' => 'Ergonomic Office Chair', 'slug' => 'ergonomic-office-chair', 'description' => 'Mesh office chair with lumbar support.'],
                'asset' => 'furniture/product-3',
                'variants' => [['sku' => 'FURN-CHAIR-003-BLK', 'price' => 1550000, 'attrs' => ['material' => 'Metal', 'upholstery_color' => 'Gray'], 'qty' => '8.000']],
            ],
        ];

        $all = array_merge($definitions, $legacy);
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'furniture-hot-deals')->first();
        $hotSkus = ['FURN-SOFA-3FAB-BEG', 'FURN-BED-KING-WAL', 'FURN-DINING-6-WAL', 'FURN-CUSHION-SET-BEG'];

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
        $living = $mk('Living Room', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Living Room') ?? '/', 20);
        $bedroom = $mk('Bedroom', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Bedroom') ?? '/', 30);
        $dining = $mk('Dining Room', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Dining Room') ?? '/', 40);
        $office = $mk('Office', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Office') ?? '/', 50);
        $brands = $mk('Brands', LinkType::External->value, '/brands', 60);
        $mk('Blog', LinkType::BlogIndex->value, null, 65);
        $mk('Sale 🔥', LinkType::External->value, '/offers', 70, null, '🔥');

        // Living dropdown
        $mk('Sofas', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Sofas') ?? '/', 21, $living->id);
        $mk('Sofa Sets', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Sofa Sets') ?? '/', 22, $living->id);
        $mk('Coffee Tables', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Coffee Tables') ?? '/', 23, $living->id);
        $mk('TV Units', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'TV Units') ?? '/', 24, $living->id);

        // Bedroom dropdown
        $mk('Beds', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Beds') ?? '/', 31, $bedroom->id);
        $mk('Wardrobes', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Wardrobes') ?? '/', 32, $bedroom->id);
        $mk('Dressing Tables', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Dressing Tables') ?? '/', 33, $bedroom->id);

        // Dining
        $mk('Dining Tables', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Dining Tables') ?? '/', 41, $dining->id);
        $mk('Dining Sets', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Dining Sets') ?? '/', 42, $dining->id);

        // Office
        $mk('Office Desks', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Office Desks') ?? '/', 51, $office->id);
        $mk('Office Chairs', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Office Chairs') ?? '/', 52, $office->id);

        // Brands
        foreach (['WoodCraft', 'Comfort Living', 'Hatil', 'Navana'] as $idx => $bName) {
            $mk($bName, LinkType::Brand->value, Str::slug($bName), 61 + $idx, $brands->id);
        }

        $mk('New Arrivals', LinkType::External->value, '/products?sort=latest', 71);
        $mk('Best Sellers', LinkType::External->value, '/products?sort=best_selling', 72);
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
            ['tenant_id' => $tenant->id, 'slug' => 'main-outlet-gulshan-furniture'],
            [
                'name' => 'Main Outlet - Gulshan (Furniture)',
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
            $src = $this->resolveAssetPath('furniture/categories/living-room');
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
                'furniture/categories/'.$stem,
                'furniture/products/'.$stem,
                'furniture/brands/'.$stem,
                'furniture/banners/'.$stem,
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
