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

final class GroceryIndustrySeeder
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
        $preset = ThemePresets::forIndustry('grocery');
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
        $this->firstOrCreateCategory($tenant, 'Fresh Produce', null, 'fresh-produce', 'grocery/categories/fol-sobji');
        $this->firstOrCreateCategory($tenant, 'Pantry Staples', null, 'pantry-staples', 'grocery/categories/chal-dal-shoshyo');
        $this->firstOrCreateCategory($tenant, 'Dairy & Eggs', null, 'dairy-eggs', 'grocery/categories/dudh-dugdhajat');
        $this->firstOrCreateCategory($tenant, 'Beverages', null, 'beverages', 'grocery/categories/panio');

        // 1. চাল, ডাল ও শস্য
        $cat1 = $this->firstOrCreateCategory($tenant, 'চাল, ডাল ও শস্য', null, 'chal-dal-shoshyo', 'grocery/categories/chal-dal-shoshyo');
        $this->firstOrCreateCategory($tenant, 'চাল', $cat1->id, 'chal', 'grocery/categories/chal-dal-shoshyo');
        $this->firstOrCreateCategory($tenant, 'ডাল', $cat1->id, 'dal', 'grocery/categories/chal-dal-shoshyo');
        $this->firstOrCreateCategory($tenant, 'আটা', $cat1->id, 'ata', 'grocery/categories/chal-dal-shoshyo');
        $this->firstOrCreateCategory($tenant, 'ময়দা', $cat1->id, 'moyda', 'grocery/categories/chal-dal-shoshyo');
        $this->firstOrCreateCategory($tenant, 'সুজি', $cat1->id, 'suji', 'grocery/categories/chal-dal-shoshyo');

        // 2. তেল ও মসলা
        $cat2 = $this->firstOrCreateCategory($tenant, 'তেল ও মসলা', null, 'tel-o-moshla', 'grocery/categories/tel-o-moshla');
        $this->firstOrCreateCategory($tenant, 'সয়াবিন তেল', $cat2->id, 'soybean-oil', 'grocery/categories/tel-o-moshla');
        $this->firstOrCreateCategory($tenant, 'সরিষার তেল', $cat2->id, 'mustard-oil', 'grocery/categories/tel-o-moshla');
        $this->firstOrCreateCategory($tenant, 'লবণ', $cat2->id, 'lobon', 'grocery/categories/tel-o-moshla');
        $this->firstOrCreateCategory($tenant, 'মরিচ', $cat2->id, 'morich', 'grocery/categories/tel-o-moshla');
        $this->firstOrCreateCategory($tenant, 'হলুদ', $cat2->id, 'holud', 'grocery/categories/tel-o-moshla');
        $this->firstOrCreateCategory($tenant, 'জিরা', $cat2->id, 'jira', 'grocery/categories/tel-o-moshla');
        $this->firstOrCreateCategory($tenant, 'অন্যান্য মসলা', $cat2->id, 'onno-moshla', 'grocery/categories/tel-o-moshla');

        // 3. চিনি ও মিষ্টিজাতীয়
        $cat3 = $this->firstOrCreateCategory($tenant, 'চিনি ও মিষ্টিজাতীয়', null, 'chini-mishti', 'grocery/categories/chini-mishti');
        $this->firstOrCreateCategory($tenant, 'চিনি', $cat3->id, 'chini', 'grocery/categories/chini-mishti');
        $this->firstOrCreateCategory($tenant, 'গুড়', $cat3->id, 'gur', 'grocery/categories/chini-mishti');
        $this->firstOrCreateCategory($tenant, 'মধু', $cat3->id, 'modhu', 'grocery/categories/chini-mishti');

        // 4. নিত্যপ্রয়োজনীয়
        $cat4 = $this->firstOrCreateCategory($tenant, 'নিত্যপ্রয়োজনীয়', null, 'nitya-proyojonio', 'grocery/categories/nitya-proyojonio');
        $this->firstOrCreateCategory($tenant, 'চা', $cat4->id, 'cha', 'grocery/categories/nitya-proyojonio');
        $this->firstOrCreateCategory($tenant, 'কফি', $cat4->id, 'coffee', 'grocery/categories/nitya-proyojonio');
        $this->firstOrCreateCategory($tenant, 'বিস্কুট', $cat4->id, 'biscuit-nitya', 'grocery/categories/nitya-proyojonio');
        $this->firstOrCreateCategory($tenant, 'নুডলস', $cat4->id, 'noodles', 'grocery/categories/nitya-proyojonio');
        $this->firstOrCreateCategory($tenant, 'পাস্তা', $cat4->id, 'pasta', 'grocery/categories/nitya-proyojonio');
        $this->firstOrCreateCategory($tenant, 'সস', $cat4->id, 'sauce', 'grocery/categories/nitya-proyojonio');

        // 5. স্ন্যাকস
        $cat5 = $this->firstOrCreateCategory($tenant, 'স্ন্যাকস', null, 'snacks', 'grocery/categories/snacks');
        $this->firstOrCreateCategory($tenant, 'চিপস', $cat5->id, 'chips', 'grocery/categories/snacks');
        $this->firstOrCreateCategory($tenant, 'চানাচুর', $cat5->id, 'chanachur', 'grocery/categories/snacks');
        $this->firstOrCreateCategory($tenant, 'চকোলেট', $cat5->id, 'chocolate', 'grocery/categories/snacks');
        $this->firstOrCreateCategory($tenant, 'ক্যান্ডি', $cat5->id, 'candy', 'grocery/categories/snacks');
        $this->firstOrCreateCategory($tenant, 'বাদাম', $cat5->id, 'badam', 'grocery/categories/snacks');

        // 6. পানীয়
        $cat6 = $this->firstOrCreateCategory($tenant, 'পানীয়', null, 'panio', 'grocery/categories/panio');
        $this->firstOrCreateCategory($tenant, 'পানি', $cat6->id, 'pani', 'grocery/categories/panio');
        $this->firstOrCreateCategory($tenant, 'জুস', $cat6->id, 'juice', 'grocery/categories/panio');
        $this->firstOrCreateCategory($tenant, 'সফট ড্রিংকস', $cat6->id, 'soft-drinks', 'grocery/categories/panio');
        $this->firstOrCreateCategory($tenant, 'এনার্জি ড্রিংক', $cat6->id, 'energy-drink', 'grocery/categories/panio');
        $this->firstOrCreateCategory($tenant, 'দুধজাত পানীয়', $cat6->id, 'dudh-panio', 'grocery/categories/panio');

        // 7. দুধ ও দুগ্ধজাত
        $cat7 = $this->firstOrCreateCategory($tenant, 'দুধ ও দুগ্ধজাত', null, 'dudh-dugdhajat', 'grocery/categories/dudh-dugdhajat');
        $this->firstOrCreateCategory($tenant, 'দুধ', $cat7->id, 'dudh', 'grocery/categories/dudh-dugdhajat');
        $this->firstOrCreateCategory($tenant, 'দই', $cat7->id, 'doi', 'grocery/categories/dudh-dugdhajat');
        $this->firstOrCreateCategory($tenant, 'মাখন', $cat7->id, 'makhon', 'grocery/categories/dudh-dugdhajat');
        $this->firstOrCreateCategory($tenant, 'পনির', $cat7->id, 'ponir', 'grocery/categories/dudh-dugdhajat');
        $this->firstOrCreateCategory($tenant, 'ঘি', $cat7->id, 'ghee', 'grocery/categories/dudh-dugdhajat');

        // 8. বেকারি
        $cat8 = $this->firstOrCreateCategory($tenant, 'বেকারি', null, 'bakery', 'grocery/categories/bakery');
        $this->firstOrCreateCategory($tenant, 'পাউরুটি', $cat8->id, 'pauruti', 'grocery/categories/bakery');
        $this->firstOrCreateCategory($tenant, 'কেক', $cat8->id, 'cake', 'grocery/categories/bakery');
        $this->firstOrCreateCategory($tenant, 'বান', $cat8->id, 'ban', 'grocery/categories/bakery');
        $this->firstOrCreateCategory($tenant, 'বেকারি বিস্কুট', $cat8->id, 'bakery-biscuit', 'grocery/categories/bakery');

        // 9. ফ্রোজেন ও প্রস্তুত খাবার
        $cat9 = $this->firstOrCreateCategory($tenant, 'ফ্রোজেন ও প্রস্তুত খাবার', null, 'frozen-ready-food', 'grocery/categories/frozen-ready-food');
        $this->firstOrCreateCategory($tenant, 'Frozen Chicken', $cat9->id, 'frozen-chicken', 'grocery/categories/frozen-ready-food');
        $this->firstOrCreateCategory($tenant, 'Frozen Fish', $cat9->id, 'frozen-fish', 'grocery/categories/frozen-ready-food');
        $this->firstOrCreateCategory($tenant, 'Nuggets', $cat9->id, 'nuggets', 'grocery/categories/frozen-ready-food');
        $this->firstOrCreateCategory($tenant, 'Sausage', $cat9->id, 'sausage', 'grocery/categories/frozen-ready-food');
        $this->firstOrCreateCategory($tenant, 'Paratha', $cat9->id, 'paratha', 'grocery/categories/frozen-ready-food');

        // 10. ফল ও সবজি
        $cat10 = $this->firstOrCreateCategory($tenant, 'ফল ও সবজি', null, 'fol-sobji', 'grocery/categories/fol-sobji');
        $this->firstOrCreateCategory($tenant, 'ফল', $cat10->id, 'fol', 'grocery/categories/fol-sobji');
        $this->firstOrCreateCategory($tenant, 'সবজি', $cat10->id, 'sobji', 'grocery/categories/fol-sobji');
        $this->firstOrCreateCategory($tenant, 'শাক', $cat10->id, 'shak', 'grocery/categories/fol-sobji');

        // 11. মাছ, মাংস ও ডিম
        $cat11 = $this->firstOrCreateCategory($tenant, 'মাছ, মাংস ও ডিম', null, 'mach-mangso-dim', 'grocery/categories/mach-mangso-dim');
        $this->firstOrCreateCategory($tenant, 'মাছ', $cat11->id, 'mach', 'grocery/categories/mach-mangso-dim');
        $this->firstOrCreateCategory($tenant, 'গরুর মাংস', $cat11->id, 'goru-mangso', 'grocery/categories/mach-mangso-dim');
        $this->firstOrCreateCategory($tenant, 'খাসির মাংস', $cat11->id, 'khasi-mangso', 'grocery/categories/mach-mangso-dim');
        $this->firstOrCreateCategory($tenant, 'মুরগি', $cat11->id, 'murgi', 'grocery/categories/mach-mangso-dim');
        $this->firstOrCreateCategory($tenant, 'ডিম', $cat11->id, 'dim', 'grocery/categories/mach-mangso-dim');

        // 12. Baby Care
        $cat12 = $this->firstOrCreateCategory($tenant, 'Baby Care', null, 'baby-care', 'grocery/categories/baby-care');
        $this->firstOrCreateCategory($tenant, 'Baby Food', $cat12->id, 'baby-food', 'grocery/categories/baby-care');
        $this->firstOrCreateCategory($tenant, 'Diapers', $cat12->id, 'diapers', 'grocery/categories/baby-care');
        $this->firstOrCreateCategory($tenant, 'Baby Wipes', $cat12->id, 'baby-wipes', 'grocery/categories/baby-care');
        $this->firstOrCreateCategory($tenant, 'Baby Care Essentials', $cat12->id, 'baby-care-essentials', 'grocery/categories/baby-care');

        // 13. Personal Care
        $cat13 = $this->firstOrCreateCategory($tenant, 'Personal Care', null, 'personal-care', 'grocery/categories/personal-care');
        $this->firstOrCreateCategory($tenant, 'সাবান', $cat13->id, 'saban', 'grocery/categories/personal-care');
        $this->firstOrCreateCategory($tenant, 'শ্যাম্পু', $cat13->id, 'shampoo', 'grocery/categories/personal-care');
        $this->firstOrCreateCategory($tenant, 'টুথপেস্ট', $cat13->id, 'toothpaste', 'grocery/categories/personal-care');
        $this->firstOrCreateCategory($tenant, 'টুথব্রাশ', $cat13->id, 'toothbrush', 'grocery/categories/personal-care');
        $this->firstOrCreateCategory($tenant, 'Face Wash', $cat13->id, 'face-wash', 'grocery/categories/personal-care');
        $this->firstOrCreateCategory($tenant, 'Personal Care অন্যান্য', $cat13->id, 'personal-other', 'grocery/categories/personal-care');

        // 14. Home & Cleaning
        $cat14 = $this->firstOrCreateCategory($tenant, 'Home & Cleaning', null, 'home-cleaning', 'grocery/categories/home-cleaning');
        $this->firstOrCreateCategory($tenant, 'ডিটারজেন্ট', $cat14->id, 'detergent', 'grocery/categories/home-cleaning');
        $this->firstOrCreateCategory($tenant, 'Dishwashing', $cat14->id, 'dishwashing', 'grocery/categories/home-cleaning');
        $this->firstOrCreateCategory($tenant, 'Floor Cleaner', $cat14->id, 'floor-cleaner', 'grocery/categories/home-cleaning');
        $this->firstOrCreateCategory($tenant, 'Toilet Cleaner', $cat14->id, 'toilet-cleaner', 'grocery/categories/home-cleaning');
        $this->firstOrCreateCategory($tenant, 'Tissue', $cat14->id, 'tissue', 'grocery/categories/home-cleaning');
        $this->firstOrCreateCategory($tenant, 'Garbage Bags', $cat14->id, 'garbage-bags', 'grocery/categories/home-cleaning');

        // 15. Household
        $cat15 = $this->firstOrCreateCategory($tenant, 'Household', null, 'household', 'grocery/categories/household');
        $this->firstOrCreateCategory($tenant, 'Kitchen Items', $cat15->id, 'kitchen-items', 'grocery/categories/household');
        $this->firstOrCreateCategory($tenant, 'Plastic Items', $cat15->id, 'plastic-items', 'grocery/categories/household');
        $this->firstOrCreateCategory($tenant, 'Battery', $cat15->id, 'battery', 'grocery/categories/household');
        $this->firstOrCreateCategory($tenant, 'Light Bulb', $cat15->id, 'light-bulb', 'grocery/categories/household');
        $this->firstOrCreateCategory($tenant, 'Household অন্যান্য', $cat15->id, 'household-other', 'grocery/categories/household');
    }

    private function seedBrands(Tenant $tenant): void
    {
        $brands = [
            'FreshMart' => 'grocery/brands/freshmart',
            'Daily Harvest' => 'grocery/brands/daily-harvest',
            'ACI' => 'grocery/brands/aci',
            'Pran' => 'grocery/brands/pran',
            'Teer' => 'grocery/brands/teer',
            'Rupchanda' => 'grocery/brands/rupchanda',
        ];
        foreach ($brands as $name => $stem) {
            $brand = $this->firstOrCreateBrand($tenant, $name);
            $this->ensureBrandLogo($brand, $stem);
        }
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, ['code' => 'weight', 'label' => 'Weight', 'data_type' => AttributeDataType::Decimal, 'unit' => 'kg', 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 1], []);
        $this->firstOrCreateAttribute($tenant, ['code' => 'unit', 'label' => 'Unit', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 2], ['kg', 'g', 'L', 'ml', 'pcs', 'pack']);
        $this->firstOrCreateAttribute($tenant, ['code' => 'pack_size', 'label' => 'Pack Size', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 3], ['Single', 'Pack of 2', 'Pack of 5', 'Family Pack']);
    }

    private function seedCampaigns(Tenant $tenant): void
    {
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'weekly-hot-deals'],
            [
                'name' => 'Weekly Hot Deals',
                'description' => 'সাপ্তাহিক সেরা অফার - চাল, তেল ও নিত্যপ্রয়োজনীয় পণ্যে বিশেষ ছাড়!',
                'accent_color' => '#16a34a',
                'short_tagline' => 'Up to 25% OFF',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(6),
            ]
        );
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'new-arrivals-grocery'],
            [
                'name' => 'New Arrivals',
                'description' => 'নতুন পণ্য - ফ্রেশ ফল, সবজি ও বেকারি আইটেম',
                'accent_color' => '#f59e0b',
                'short_tagline' => 'Just Arrived',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->addDays(20),
            ]
        );
    }

    private function seedHomepage(Tenant $tenant): void
    {
        // Fix legacy: old "Fresh Arrivals" at sort 4 was hero's predecessor — deactivate so Hero (10) is first
        HomepageSection::where('tenant_id', $tenant->id)
            ->where('type', 'product_grid')
            ->where('title', 'Fresh Arrivals')
            ->where('sort_order', 4)
            ->update(['is_active' => false, 'sort_order' => 999]);

        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Hero Banner', 10, ['placement' => 'hero']);
        $this->firstOrCreateHomepageSection($tenant, 'trust_badges', 'Our Promise', 20);
        // Explicit category_ids ensures all 15 Bengali mains show even if some have 0 products yet (visual completeness)
        $mainCategoryIds = Category::query()->where('tenant_id', $tenant->id)->whereNull('parent_id')->whereIn('slug', ['chal-dal-shoshyo', 'tel-o-moshla', 'chini-mishti', 'nitya-proyojonio', 'snacks', 'panio', 'dudh-dugdhajat', 'bakery', 'frozen-ready-food', 'fol-sobji', 'mach-mangso-dim', 'baby-care', 'personal-care', 'home-cleaning', 'household'])->orderBy('id')->pluck('id')->all();
        $catConfig = ['source' => 'category', 'limit' => 15];
        if ($mainCategoryIds !== []) {
            $catConfig['category_ids'] = $mainCategoryIds;
        }
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Category', 30, $catConfig);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Popular Picks', 40, ['data_source' => 'featured', 'limit' => 8]);

        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Brand', 50, ['source' => 'brand', 'limit' => 6]);

        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'weekly-hot-deals')->first();
        $hot = $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Hot Deals - Weekly Offer', 60, ['data_source' => 'campaign', 'limit' => 8]);
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
        $this->firstOrCreateBanner($tenant, 'Hero Banner 1', 'hero', 'grocery/banners/hero-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Hero Banner 2', 'hero', 'grocery/banners/hero-banner-2', 1);
        $this->firstOrCreateBanner($tenant, 'Promotional Banner 1', 'promo', 'grocery/banners/promo-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Flash Sale Banner', 'promo', 'grocery/banners/flash-sale-banner', 1);
        $this->firstOrCreateBanner($tenant, 'Grocery Hero', 'hero', 'grocery/hero', 10);
    }

    private function seedProducts(Tenant $tenant): void
    {
        $cat = fn (string $name): ?Category => Category::query()->where('tenant_id', $tenant->id)->where('name', $name)->first();
        $brand = fn (string $name): ?Brand => Brand::query()->where('tenant_id', $tenant->id)->where('slug', Str::slug($name))->first();

        // Brand lookups with fallback
        $brandFresh = $brand('FreshMart') ?? $brand('ACI');
        $brandAci = $brand('ACI') ?? $brandFresh;
        $brandTeer = $brand('Teer') ?? $brandFresh;
        $brandRup = $brand('Rupchanda') ?? $brandFresh;

        $definitions = [
            // চাল, ডাল ও শস্য — 5
            [
                'model_number' => 'GROC-MINIKET-5KG',
                'category_id' => $cat('চাল')?->id ?? $cat('চাল, ডাল ও শস্য')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 380000, 'sold_count' => 210, 'is_featured' => true,
                'en' => ['name' => 'Miniket Rice — 5 KG', 'slug' => 'miniket-rice-5-kg', 'description' => 'Premium Miniket rice, perfect for daily cooking. 5 KG family pack.'],
                'asset' => 'grocery/products/miniket-rice-5kg',
                'variants' => [['sku' => 'GROC-MINIKET-5KG', 'price' => 380000, 'qty' => '40.000']],
            ],
            [
                'model_number' => 'GROC-NAZIR-5KG',
                'category_id' => $cat('চাল')?->id ?? $cat('চাল, ডাল ও শস্য')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 420000, 'sold_count' => 165, 'is_featured' => true,
                'en' => ['name' => 'Nazirshail Rice — 5 KG', 'slug' => 'nazirshail-rice-5-kg', 'description' => 'Aromatic Nazirshail rice, long grain.'],
                'asset' => 'grocery/products/nazirshail-rice-5kg',
                'variants' => [['sku' => 'GROC-NAZIR-5KG', 'price' => 420000, 'qty' => '35.000']],
            ],
            [
                'model_number' => 'GROC-CHINI-1KG',
                'category_id' => $cat('চাল')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 185000, 'sold_count' => 98,
                'en' => ['name' => 'Chinigura Rice — 1 KG', 'slug' => 'chinigura-rice-1-kg', 'description' => 'Fragrant Chinigura for polao & kheer.'],
                'asset' => 'grocery/products/chinigura-rice-1kg',
                'variants' => [['sku' => 'GROC-CHINI-1KG', 'price' => 185000, 'qty' => '50.000']],
            ],
            [
                'model_number' => 'GROC-MASOOR-1KG',
                'category_id' => $cat('ডাল')?->id,
                'brand_id' => $brandAci?->id,
                'base_price' => 135000, 'sold_count' => 180, 'is_best_selling' => true,
                'en' => ['name' => 'Masoor Dal — 1 KG', 'slug' => 'masoor-dal-1-kg', 'description' => 'Premium Masoor dal, protein rich.'],
                'asset' => 'grocery/products/masoor-dal-1kg',
                'variants' => [['sku' => 'GROC-MASOOR-1KG', 'price' => 135000, 'qty' => '60.000']],
            ],
            [
                'model_number' => 'GROC-CHOLA-1KG',
                'category_id' => $cat('ডাল')?->id,
                'brand_id' => $brandAci?->id,
                'base_price' => 120000, 'sold_count' => 75,
                'en' => ['name' => 'Chickpea / Chola — 1 KG', 'slug' => 'chickpea-chola-1-kg', 'description' => 'Deshi Chola for ghugni & curry.'],
                'asset' => 'grocery/products/chickpea-1kg',
                'variants' => [['sku' => 'GROC-CHOLA-1KG', 'price' => 120000, 'qty' => '45.000']],
            ],
            // তেল, লবণ ও মসলা — 5
            [
                'model_number' => 'GROC-SOY-1L',
                'category_id' => $cat('সয়াবিন তেল')?->id ?? $cat('তেল ও মসলা')?->id,
                'brand_id' => $brandRup?->id,
                'base_price' => 185000, 'sold_count' => 320, 'is_featured' => true, 'is_best_selling' => true,
                'en' => ['name' => 'Soybean Oil — 1 L', 'slug' => 'soybean-oil-1-l', 'description' => 'Fortified soybean oil, ideal for everyday cooking.'],
                'asset' => 'grocery/products/soybean-oil-1l',
                'variants' => [['sku' => 'GROC-SOY-1L', 'price' => 185000, 'qty' => '80.000']],
            ],
            [
                'model_number' => 'GROC-SOY-2L',
                'category_id' => $cat('সয়াবিন তেল')?->id,
                'brand_id' => $brandRup?->id,
                'base_price' => 350000, 'is_featured' => true, 'sold_count' => 195,
                'en' => ['name' => 'Soybean Oil — 2 L', 'slug' => 'soybean-oil-2-l', 'description' => 'Family pack soybean oil - value for money.'],
                'asset' => 'grocery/products/soybean-oil-2l',
                'variants' => [['sku' => 'GROC-SOY-2L', 'price' => 350000, 'qty' => '55.000']],
            ],
            [
                'model_number' => 'GROC-MUST-500ML',
                'category_id' => $cat('সরিষার তেল')?->id,
                'brand_id' => $brandTeer?->id,
                'base_price' => 165000, 'sold_count' => 88,
                'en' => ['name' => 'Mustard Oil — 500 ML', 'slug' => 'mustard-oil-500-ml', 'description' => 'Pure mustard oil, cold pressed.'],
                'asset' => 'grocery/products/mustard-oil-500ml',
                'variants' => [['sku' => 'GROC-MUST-500ML', 'price' => 165000, 'qty' => '40.000']],
            ],
            [
                'model_number' => 'GROC-HOLUD-200GM',
                'category_id' => $cat('হলুদ')?->id,
                'brand_id' => $brandAci?->id,
                'base_price' => 65000, 'sold_count' => 142,
                'en' => ['name' => 'Turmeric Powder — 200 GM', 'slug' => 'turmeric-powder-200-gm', 'description' => 'Pure turmeric powder, vibrant color & aroma.'],
                'asset' => 'grocery/products/turmeric-200gm',
                'variants' => [['sku' => 'GROC-HOLUD-200GM', 'price' => 65000, 'qty' => '70.000']],
            ],
            [
                'model_number' => 'GROC-MORICH-200GM',
                'category_id' => $cat('মরিচ')?->id,
                'brand_id' => $brandAci?->id,
                'base_price' => 72000, 'sold_count' => 130,
                'en' => ['name' => 'Chili Powder — 200 GM', 'slug' => 'chili-powder-200-gm', 'description' => 'Hot chili powder, premium quality.'],
                'asset' => 'grocery/products/chili-200gm',
                'variants' => [['sku' => 'GROC-MORICH-200GM', 'price' => 72000, 'qty' => '65.000']],
            ],
            // Dairy & Eggs — 4
            [
                'model_number' => 'GROC-UHT-1L',
                'category_id' => $cat('দুধ')?->id ?? $cat('দুধ ও দুগ্ধজাত')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 95000, 'sold_count' => 240, 'is_featured' => true,
                'en' => ['name' => 'UHT Full Cream Milk — 1 L', 'slug' => 'uht-full-cream-milk-1-l', 'description' => 'UHT full cream milk, long shelf life.'],
                'asset' => 'grocery/products/uht-milk-1l',
                'variants' => [['sku' => 'GROC-UHT-1L', 'price' => 95000, 'qty' => '90.000']],
            ],
            [
                'model_number' => 'GROC-POWDER-500GM',
                'category_id' => $cat('দুধ')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 420000, 'sold_count' => 110,
                'en' => ['name' => 'Powder Milk — 500 GM', 'slug' => 'powder-milk-500-gm', 'description' => 'Instant full cream powder milk.'],
                'asset' => 'grocery/products/powder-milk-500gm',
                'variants' => [['sku' => 'GROC-POWDER-500GM', 'price' => 420000, 'qty' => '35.000']],
            ],
            [
                'model_number' => 'GROC-YOGURT-500GM',
                'category_id' => $cat('দই')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 95000, 'sold_count' => 75,
                'en' => ['name' => 'Yogurt — 500 GM', 'slug' => 'yogurt-500-gm', 'description' => 'Creamy sweet yogurt, farm fresh.'],
                'asset' => 'grocery/products/yogurt-500gm',
                'variants' => [['sku' => 'GROC-YOGURT-500GM', 'price' => 95000, 'qty' => '40.000']],
            ],
            [
                'model_number' => 'GROC-EGGS-12PCS',
                'category_id' => $cat('ডিম')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 145000, 'sold_count' => 410, 'is_best_selling' => true, 'is_featured' => true,
                'en' => ['name' => 'Fresh Eggs — 12 pcs', 'slug' => 'fresh-eggs-12-pcs', 'description' => 'Farm fresh eggs, 12 pcs tray.'],
                'asset' => 'grocery/products/eggs-12pcs',
                'variants' => [['sku' => 'GROC-EGGS-12PCS', 'price' => 145000, 'qty' => '120.000']],
            ],
            // Snacks & Biscuits — 4
            [
                'model_number' => 'GROC-CHOC-BIS-120GM',
                'category_id' => $cat('বিস্কুট')?->id ?? $cat('নিত্যপ্রয়োজনীয়')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 45000, 'sold_count' => 95,
                'en' => ['name' => 'Chocolate Cream Biscuits — 120 GM', 'slug' => 'chocolate-cream-biscuits-120-gm', 'description' => 'Crispy chocolate cream biscuits.'],
                'asset' => 'grocery/products/chocolate-biscuits-120gm',
                'variants' => [['sku' => 'GROC-CHOC-BIS-120GM', 'price' => 45000, 'qty' => '70.000']],
            ],
            [
                'model_number' => 'GROC-BUTTER-200GM',
                'category_id' => $cat('বিস্কুট')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 85000, 'sold_count' => 88,
                'en' => ['name' => 'Butter Cookies — 200 GM', 'slug' => 'butter-cookies-200-gm', 'description' => 'Buttery cookies, melt in mouth.'],
                'asset' => 'grocery/products/butter-cookies-200gm',
                'variants' => [['sku' => 'GROC-BUTTER-200GM', 'price' => 85000, 'qty' => '55.000']],
            ],
            [
                'model_number' => 'GROC-CHIPS-50GM',
                'category_id' => $cat('চিপস')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 25000, 'sold_count' => 260, 'is_best_selling' => true,
                'en' => ['name' => 'Potato Chips — 50 GM', 'slug' => 'potato-chips-50-gm', 'description' => 'Crunchy potato chips, salted.'],
                'asset' => 'grocery/products/chips-50gm',
                'variants' => [['sku' => 'GROC-CHIPS-50GM', 'price' => 25000, 'qty' => '150.000']],
            ],
            [
                'model_number' => 'GROC-CHANA-300GM',
                'category_id' => $cat('চানাচুর')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 85000, 'sold_count' => 105,
                'en' => ['name' => 'Chanachur — 300 GM', 'slug' => 'chanachur-300-gm', 'description' => 'Spicy chanachur, perfect snack.'],
                'asset' => 'grocery/products/chanachur-300gm',
                'variants' => [['sku' => 'GROC-CHANA-300GM', 'price' => 85000, 'qty' => '60.000']],
            ],
            // Beverages — 3
            [
                'model_number' => 'GROC-WATER-1_5L',
                'category_id' => $cat('পানি')?->id ?? $cat('পানীয়')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 30000, 'sold_count' => 380, 'is_best_selling' => true,
                'en' => ['name' => 'Mineral Water — 1.5 L', 'slug' => 'mineral-water-1-5-l', 'description' => 'Pure mineral water, 1.5 litre bottle.'],
                'asset' => 'grocery/products/water-1-5l',
                'variants' => [['sku' => 'GROC-WATER-1_5L', 'price' => 30000, 'qty' => '200.000']],
            ],
            [
                'model_number' => 'GROC-JUICE-1L',
                'category_id' => $cat('জুস')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 95000, 'sold_count' => 115,
                'en' => ['name' => 'Mango Juice — 1 L', 'slug' => 'mango-juice-1-l', 'description' => 'Fresh mango juice, no added sugar.'],
                'asset' => 'grocery/products/mango-juice-1l',
                'variants' => [['sku' => 'GROC-JUICE-1L', 'price' => 95000, 'qty' => '45.000']],
            ],
            [
                'model_number' => 'GROC-COFFEE-100GM',
                'category_id' => $cat('কফি')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 250000, 'is_featured' => true, 'sold_count' => 65,
                'en' => ['name' => 'Instant Coffee — 100 GM', 'slug' => 'instant-coffee-100-gm', 'description' => 'Premium instant coffee, rich aroma.'],
                'asset' => 'grocery/products/coffee-100gm',
                'variants' => [['sku' => 'GROC-COFFEE-100GM', 'price' => 250000, 'qty' => '30.000']],
            ],
            // Instant & Packaged Food — 3
            [
                'model_number' => 'GROC-NOODLES-8PACK',
                'category_id' => $cat('নুডলস')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 185000, 'is_featured' => true, 'sold_count' => 220,
                'en' => ['name' => 'Instant Noodles — 8 Pack', 'slug' => 'instant-noodles-8-pack', 'description' => '8 pack instant noodles, masala flavor.'],
                'asset' => 'grocery/products/noodles-8pack',
                'variants' => [['sku' => 'GROC-NOODLES-8PACK', 'price' => 185000, 'qty' => '80.000']],
            ],
            [
                'model_number' => 'GROC-PASTA-500GM',
                'category_id' => $cat('পাস্তা')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 120000, 'sold_count' => 55,
                'en' => ['name' => 'Pasta — 500 GM', 'slug' => 'pasta-500-gm', 'description' => 'Penne pasta, easy to cook.'],
                'asset' => 'grocery/products/pasta-500gm',
                'variants' => [['sku' => 'GROC-PASTA-500GM', 'price' => 120000, 'qty' => '40.000']],
            ],
            [
                'model_number' => 'GROC-KETCHUP-500GM',
                'category_id' => $cat('সস')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 95000, 'sold_count' => 70,
                'en' => ['name' => 'Tomato Ketchup — 500 GM', 'slug' => 'tomato-ketchup-500-gm', 'description' => 'Tangy tomato ketchup, 500gm bottle.'],
                'asset' => 'grocery/products/ketchup-500gm',
                'variants' => [['sku' => 'GROC-KETCHUP-500GM', 'price' => 95000, 'qty' => '35.000']],
            ],
            // Personal Care — 3
            [
                'model_number' => 'GROC-SHAMPOO-340ML',
                'category_id' => $cat('শ্যাম্পু')?->id ?? $cat('Personal Care')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 280000, 'sold_count' => 90,
                'en' => ['name' => 'Herbal Shampoo — 340 ML', 'slug' => 'herbal-shampoo-340-ml', 'description' => 'Herbal shampoo for healthy hair.'],
                'asset' => 'grocery/products/shampoo-340ml',
                'variants' => [['sku' => 'GROC-SHAMPOO-340ML', 'price' => 280000, 'qty' => '25.000']],
            ],
            [
                'model_number' => 'GROC-SOAP-100GM',
                'category_id' => $cat('সাবান')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 45000, 'sold_count' => 155,
                'en' => ['name' => 'Bathing Soap — 100 GM', 'slug' => 'bathing-soap-100-gm', 'description' => 'Moisturizing bathing soap.'],
                'asset' => 'grocery/products/soap-100gm',
                'variants' => [['sku' => 'GROC-SOAP-100GM', 'price' => 45000, 'qty' => '100.000']],
            ],
            [
                'model_number' => 'GROC-PASTE-150GM',
                'category_id' => $cat('টুথপেস্ট')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 85000, 'sold_count' => 120,
                'en' => ['name' => 'Toothpaste — 150 GM', 'slug' => 'toothpaste-150-gm', 'description' => 'Fluoride toothpaste for strong teeth.'],
                'asset' => 'grocery/products/toothpaste-150gm',
                'variants' => [['sku' => 'GROC-PASTE-150GM', 'price' => 85000, 'qty' => '60.000']],
            ],
            // Home & Cleaning — 3
            [
                'model_number' => 'GROC-DETERGENT-1KG',
                'category_id' => $cat('ডিটারজেন্ট')?->id ?? $cat('Home & Cleaning')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 220000, 'sold_count' => 140, 'is_featured' => true,
                'en' => ['name' => 'Laundry Detergent — 1 KG', 'slug' => 'laundry-detergent-1-kg', 'description' => 'Powerful detergent for bright clothes.'],
                'asset' => 'grocery/products/detergent-1kg',
                'variants' => [['sku' => 'GROC-DETERGENT-1KG', 'price' => 220000, 'qty' => '45.000']],
            ],
            [
                'model_number' => 'GROC-DISH-500ML',
                'category_id' => $cat('Dishwashing')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 95000, 'sold_count' => 85,
                'en' => ['name' => 'Dishwashing Liquid — 500 ML', 'slug' => 'dishwashing-liquid-500-ml', 'description' => 'Lemon dishwashing liquid, cuts grease.'],
                'asset' => 'grocery/products/dishwashing-500ml',
                'variants' => [['sku' => 'GROC-DISH-500ML', 'price' => 95000, 'qty' => '35.000']],
            ],
            [
                'model_number' => 'GROC-TISSUE-200S',
                'category_id' => $cat('Tissue')?->id,
                'brand_id' => $brandFresh?->id,
                'base_price' => 75000, 'sold_count' => 100,
                'en' => ['name' => 'Facial Tissue — 200 Sheets', 'slug' => 'facial-tissue-200-sheets', 'description' => 'Soft facial tissue box, 200 sheets.'],
                'asset' => 'grocery/products/tissue-200sheets',
                'variants' => [['sku' => 'GROC-TISSUE-200S', 'price' => 75000, 'qty' => '50.000']],
            ],
        ];

        // Legacy products for backward compat (tests expect GROC-APPLE-1KG etc)
        $legacy = [
            [
                'model_number' => 'GROC-APPLE-1KG',
                'category_id' => $cat('Fresh Produce')?->id,
                'brand_id' => $brand('FreshMart')?->id,
                'base_price' => 35000, 'sold_count' => 42, 'is_featured' => true,
                'en' => ['name' => 'Fresh Red Apples 1kg', 'slug' => 'fresh-red-apples-1kg', 'description' => 'Crisp and juicy red apples, handpicked daily.'],
                'asset' => 'grocery/product-1',
                'variants' => [['sku' => 'GROC-APPLE-1KG', 'price' => 35000, 'qty' => '15.000']],
            ],
            [
                'model_number' => 'GROC-RICE-5KG',
                'category_id' => $cat('Pantry Staples')?->id,
                'brand_id' => $brand('FreshMart')?->id,
                'base_price' => 520000, 'sold_count' => 38, 'is_featured' => true,
                'en' => ['name' => 'Basmati Rice 5kg Pack', 'slug' => 'basmati-rice-5kg-pack', 'description' => 'Aromatic long-grain Basmati rice, perfect for biryani.'],
                'asset' => 'grocery/product-2',
                'variants' => [['sku' => 'GROC-RICE-5KG', 'price' => 520000, 'qty' => '20.000']],
            ],
            [
                'model_number' => 'GROC-MILK-1L',
                'category_id' => $cat('Dairy & Eggs')?->id,
                'brand_id' => $brand('FreshMart')?->id,
                'base_price' => 9500, 'sold_count' => 55, 'is_featured' => true,
                'en' => ['name' => 'Fresh Milk 1L', 'slug' => 'fresh-milk-1l', 'description' => 'Farm fresh pasteurized milk, rich in calcium.'],
                'asset' => 'grocery/product-3',
                'variants' => [['sku' => 'GROC-MILK-1L', 'price' => 9500, 'qty' => '30.000']],
            ],
        ];

        $all = array_merge($definitions, $legacy);
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'weekly-hot-deals')->first();
        $hotSkus = ['GROC-SOY-1L', 'GROC-SOY-2L', 'GROC-EGGS-12PCS', 'GROC-CHIPS-50GM', 'GROC-MINIKET-5KG', 'GROC-WATER-1_5L'];

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
                    $inflated = (int) round((int) $variant->price * 1.20);
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

        // Cleanup: remove extra "All Categories" that duplicates the default mega menu (header already has All Categories)
        $menuId = $menu->id;
        $extraAll = MenuItem::where('tenant_id', $tenant->id)
            ->where('menu_id', $menuId)
            ->where('label', 'All Categories')
            ->where('parent_id', null)
            ->where('sort_order', 18)
            ->first();
        if ($extraAll) {
            // Delete children first via cascade, then parent
            MenuItem::where('tenant_id', $tenant->id)->where('parent_id', $extraAll->id)->delete();
            $extraAll->delete();
        }

        $home = $mk('Home', LinkType::External->value, '/', 10);
        // Quick top categories (most shopped)
        $catChal = $mk('চাল, ডাল ও শস্য', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'চাল, ডাল ও শস্য') ?? '/', 20);
        $catTel = $mk('তেল ও মসলা', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'তেল ও মসলা') ?? '/', 30);
        $catPanio = $mk('পানীয়', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'পানীয়') ?? '/', 40);
        $catDudh = $mk('দুধ ও দুগ্ধজাত', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'দুধ ও দুগ্ধজাত') ?? '/', 50);
        $brands = $mk('Brands', LinkType::External->value, '/brands', 60);
        $mk('Blog', LinkType::BlogIndex->value, null, 65);
        $mk('Offers 🔥', LinkType::External->value, '/offers', 70, null, '🔥');

        // Submenus for চাল, ডাল
        $mk('চাল', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'চাল') ?? '/', 21, $catChal->id);
        $mk('ডাল', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'ডাল') ?? '/', 22, $catChal->id);
        $mk('আটা', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'আটা') ?? '/', 23, $catChal->id);
        // তেল submenu
        $mk('সয়াবিন তেল', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'সয়াবিন তেল') ?? '/', 31, $catTel->id);
        $mk('সরিষার তেল', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'সরিষার তেল') ?? '/', 32, $catTel->id);
        $mk('মসলা', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'অন্যান্য মসলা') ?? '/', 33, $catTel->id);
        // পানীয় submenu
        $mk('পানি', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'পানি') ?? '/', 41, $catPanio->id);
        $mk('জুস', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'জুস') ?? '/', 42, $catPanio->id);
        // Baby & Personal as extra top if needed
        $mk('Baby Care', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Baby Care') ?? '/', 55, null);
        $mk('Personal Care', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Personal Care') ?? '/', 56, null);

        foreach (['FreshMart', 'ACI', 'Pran', 'Teer', 'Rupchanda'] as $idx => $bName) {
            $mk($bName, LinkType::Brand->value, Str::slug($bName), 61 + $idx, $brands->id);
        }
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
            ['type' => ShippingMethodType::FlatRate, 'cost' => 5000, 'is_active' => true, 'sort_order' => 1]
        );
        ShippingMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Express Delivery (Inside Dhaka)'],
            ['type' => ShippingMethodType::FlatRate, 'cost' => 10000, 'is_active' => true, 'sort_order' => 2]
        );
        ShippingMethod::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Store Pickup'],
            ['type' => ShippingMethodType::Pickup, 'cost' => 0, 'is_active' => true, 'sort_order' => 3]
        );

        Outlet::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'main-outlet-gulshan-grocery'],
            [
                'name' => 'Main Outlet - Gulshan (Grocery)',
                'address_line_1' => 'House 123, Road 11, Gulshan-1',
                'city' => 'Dhaka',
                'phone' => '01XXXXXXXXX',
                'email' => 'support@'.$tenant->subdomain.'.test',
                'opening_hours' => ['sat-thu' => '8:00 AM - 10:00 PM', 'fri' => '9:00 AM - 10:00 PM'],
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

        // If slug mismatch due to prior empty slug, patch it (merchant edits preserved if already has proper slug)
        if ($category->slug === '' || $category->slug !== $slug) {
            // Only patch if current slug is empty or was auto-generated empty; avoid overwriting merchant custom slug if already set to same logical slug
            if ($category->slug === '' || $category->slug === Str::slug($name)) {
                // Check uniqueness before update
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
            $src = $this->resolveAssetPath('grocery/categories/chal-dal-shoshyo');
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
                'grocery/categories/'.$stem,
                'grocery/products/'.$stem,
                'grocery/brands/'.$stem,
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
