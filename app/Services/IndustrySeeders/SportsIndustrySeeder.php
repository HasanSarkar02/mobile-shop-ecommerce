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

final class SportsIndustrySeeder
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
        $preset = ThemePresets::forIndustry('sports');
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
        $this->firstOrCreateCategory($tenant, 'Sports Equipment', null, 'sports-equipment', 'sports/categories/sports-equipment');
        $this->firstOrCreateCategory($tenant, 'Apparel', null, 'apparel', 'sports/categories/apparel');
        $this->firstOrCreateCategory($tenant, 'Footwear', null, 'footwear', 'sports/categories/footwear');
        $this->firstOrCreateCategory($tenant, 'Accessories', null, 'accessories', 'sports/categories/accessories');

        // Cricket
        $cricket = $this->firstOrCreateCategory($tenant, 'Cricket', null, 'cricket', 'sports/categories/cricket');
        $this->firstOrCreateCategory($tenant, 'Cricket Bats', $cricket->id, 'cricket-bats', 'sports/categories/cricket-bats');
        $this->firstOrCreateCategory($tenant, 'Cricket Balls', $cricket->id, 'cricket-balls', 'sports/categories/cricket-balls');
        $this->firstOrCreateCategory($tenant, 'Cricket Batting Gloves', $cricket->id, 'cricket-batting-gloves', 'sports/categories/cricket-batting-gloves');
        $this->firstOrCreateCategory($tenant, 'Cricket Bowling', $cricket->id, 'cricket-bowling', 'sports/categories/cricket-bowling');
        $this->firstOrCreateCategory($tenant, 'Cricket Wickets & Stumps', $cricket->id, 'cricket-wickets-stumps', 'sports/categories/cricket-wickets-stumps');
        $this->firstOrCreateCategory($tenant, 'Cricket Protective Gear', $cricket->id, 'cricket-protective-gear', 'sports/categories/cricket-protective-gear');
        $this->firstOrCreateCategory($tenant, 'Cricket Bags', $cricket->id, 'cricket-bags', 'sports/categories/cricket-bags');
        $this->firstOrCreateCategory($tenant, 'Cricket Accessories', $cricket->id, 'cricket-accessories', 'sports/categories/cricket-accessories');

        // Football
        $football = $this->firstOrCreateCategory($tenant, 'Football', null, 'football', 'sports/categories/football');
        $this->firstOrCreateCategory($tenant, 'Footballs', $football->id, 'footballs', 'sports/categories/footballs');
        $this->firstOrCreateCategory($tenant, 'Football Boots', $football->id, 'football-boots', 'sports/categories/football-boots');
        $this->firstOrCreateCategory($tenant, 'Football Jerseys', $football->id, 'football-jerseys', 'sports/categories/football-jerseys');
        $this->firstOrCreateCategory($tenant, 'Football Gloves', $football->id, 'football-gloves', 'sports/categories/football-gloves');
        $this->firstOrCreateCategory($tenant, 'Shin Guards', $football->id, 'shin-guards', 'sports/categories/shin-guards');
        $this->firstOrCreateCategory($tenant, 'Football Accessories', $football->id, 'football-accessories', 'sports/categories/football-accessories');

        // Badminton
        $badminton = $this->firstOrCreateCategory($tenant, 'Badminton', null, 'badminton', 'sports/categories/badminton');
        $this->firstOrCreateCategory($tenant, 'Badminton Rackets', $badminton->id, 'badminton-rackets', 'sports/categories/badminton-rackets');
        $this->firstOrCreateCategory($tenant, 'Shuttlecocks', $badminton->id, 'shuttlecocks', 'sports/categories/shuttlecocks');
        $this->firstOrCreateCategory($tenant, 'Badminton Shoes', $badminton->id, 'badminton-shoes', 'sports/categories/badminton-shoes');
        $this->firstOrCreateCategory($tenant, 'Badminton Nets', $badminton->id, 'badminton-nets', 'sports/categories/badminton-nets');
        $this->firstOrCreateCategory($tenant, 'Badminton Accessories', $badminton->id, 'badminton-accessories', 'sports/categories/badminton-accessories');

        // Tennis
        $tennis = $this->firstOrCreateCategory($tenant, 'Tennis', null, 'tennis', 'sports/categories/tennis');
        $this->firstOrCreateCategory($tenant, 'Tennis Rackets', $tennis->id, 'tennis-rackets', 'sports/categories/tennis-rackets');
        $this->firstOrCreateCategory($tenant, 'Tennis Balls', $tennis->id, 'tennis-balls', 'sports/categories/tennis-balls');
        $this->firstOrCreateCategory($tenant, 'Tennis Shoes', $tennis->id, 'tennis-shoes', 'sports/categories/tennis-shoes');
        $this->firstOrCreateCategory($tenant, 'Tennis Bags', $tennis->id, 'tennis-bags', 'sports/categories/tennis-bags');
        $this->firstOrCreateCategory($tenant, 'Tennis Accessories', $tennis->id, 'tennis-accessories', 'sports/categories/tennis-accessories');

        // Basketball
        $basketball = $this->firstOrCreateCategory($tenant, 'Basketball', null, 'basketball', 'sports/categories/basketball');
        $this->firstOrCreateCategory($tenant, 'Basketballs', $basketball->id, 'basketballs', 'sports/categories/basketballs');
        $this->firstOrCreateCategory($tenant, 'Basketball Shoes', $basketball->id, 'basketball-shoes', 'sports/categories/basketball-shoes');
        $this->firstOrCreateCategory($tenant, 'Basketball Jerseys', $basketball->id, 'basketball-jerseys', 'sports/categories/basketball-jerseys');
        $this->firstOrCreateCategory($tenant, 'Basketball Hoops', $basketball->id, 'basketball-hoops', 'sports/categories/basketball-hoops');
        $this->firstOrCreateCategory($tenant, 'Basketball Accessories', $basketball->id, 'basketball-accessories', 'sports/categories/basketball-accessories');

        // Volleyball
        $volleyball = $this->firstOrCreateCategory($tenant, 'Volleyball', null, 'volleyball', 'sports/categories/volleyball');
        $this->firstOrCreateCategory($tenant, 'Volleyballs', $volleyball->id, 'volleyballs', 'sports/categories/volleyballs');
        $this->firstOrCreateCategory($tenant, 'Volleyball Nets', $volleyball->id, 'volleyball-nets', 'sports/categories/volleyball-nets');
        $this->firstOrCreateCategory($tenant, 'Volleyball Shoes', $volleyball->id, 'volleyball-shoes', 'sports/categories/volleyball-shoes');
        $this->firstOrCreateCategory($tenant, 'Volleyball Accessories', $volleyball->id, 'volleyball-accessories', 'sports/categories/volleyball-accessories');

        // Table Tennis
        $tt = $this->firstOrCreateCategory($tenant, 'Table Tennis', null, 'table-tennis', 'sports/categories/table-tennis');
        $this->firstOrCreateCategory($tenant, 'Table Tennis Bats', $tt->id, 'table-tennis-bats', 'sports/categories/table-tennis-bats');
        $this->firstOrCreateCategory($tenant, 'Table Tennis Balls', $tt->id, 'table-tennis-balls', 'sports/categories/table-tennis-balls');
        $this->firstOrCreateCategory($tenant, 'Table Tennis Tables', $tt->id, 'table-tennis-tables', 'sports/categories/table-tennis-tables');
        $this->firstOrCreateCategory($tenant, 'Table Tennis Nets', $tt->id, 'table-tennis-nets', 'sports/categories/table-tennis-nets');
        $this->firstOrCreateCategory($tenant, 'Table Tennis Accessories', $tt->id, 'table-tennis-accessories', 'sports/categories/table-tennis-accessories');

        // Fitness & Gym
        $fitness = $this->firstOrCreateCategory($tenant, 'Fitness & Gym', null, 'fitness-gym', 'sports/categories/fitness-gym');
        $this->firstOrCreateCategory($tenant, 'Dumbbells', $fitness->id, 'dumbbells', 'sports/categories/dumbbells');
        $this->firstOrCreateCategory($tenant, 'Kettlebells', $fitness->id, 'kettlebells', 'sports/categories/kettlebells');
        $this->firstOrCreateCategory($tenant, 'Resistance Bands', $fitness->id, 'resistance-bands', 'sports/categories/resistance-bands');
        $this->firstOrCreateCategory($tenant, 'Yoga Mats', $fitness->id, 'yoga-mats', 'sports/categories/yoga-mats');
        $this->firstOrCreateCategory($tenant, 'Exercise Equipment', $fitness->id, 'exercise-equipment', 'sports/categories/exercise-equipment');
        $this->firstOrCreateCategory($tenant, 'Gym Gloves', $fitness->id, 'gym-gloves', 'sports/categories/gym-gloves');
        $this->firstOrCreateCategory($tenant, 'Gym Bags', $fitness->id, 'gym-bags', 'sports/categories/gym-bags');
        $this->firstOrCreateCategory($tenant, 'Fitness Accessories', $fitness->id, 'fitness-accessories', 'sports/categories/fitness-accessories');

        // Running
        $running = $this->firstOrCreateCategory($tenant, 'Running', null, 'running', 'sports/categories/running');
        $this->firstOrCreateCategory($tenant, 'Running Shoes', $running->id, 'running-shoes', 'sports/categories/running-shoes');
        $this->firstOrCreateCategory($tenant, 'Running Clothes', $running->id, 'running-clothes', 'sports/categories/running-clothes');
        $this->firstOrCreateCategory($tenant, 'Running Accessories', $running->id, 'running-accessories', 'sports/categories/running-accessories');
        $this->firstOrCreateCategory($tenant, 'Sports Socks', $running->id, 'sports-socks-running', 'sports/categories/sports-socks');
        $this->firstOrCreateCategory($tenant, 'Running Bags', $running->id, 'running-bags', 'sports/categories/running-bags');

        // Sportswear
        $sportswear = $this->firstOrCreateCategory($tenant, 'Sportswear', null, 'sportswear', 'sports/categories/sportswear');
        $this->firstOrCreateCategory($tenant, 'Jerseys', $sportswear->id, 'jerseys', 'sports/categories/jerseys');
        $this->firstOrCreateCategory($tenant, 'T-Shirts', $sportswear->id, 't-shirts-sportswear', 'sports/categories/t-shirts');
        $this->firstOrCreateCategory($tenant, 'Shorts', $sportswear->id, 'shorts-sportswear', 'sports/categories/shorts');
        $this->firstOrCreateCategory($tenant, 'Track Pants', $sportswear->id, 'track-pants', 'sports/categories/track-pants');
        $this->firstOrCreateCategory($tenant, 'Tracksuits', $sportswear->id, 'tracksuits', 'sports/categories/tracksuits');
        $this->firstOrCreateCategory($tenant, 'Sports Jackets', $sportswear->id, 'sports-jackets', 'sports/categories/sports-jackets');
        $this->firstOrCreateCategory($tenant, 'Sports Socks ', $sportswear->id, 'sports-socks', 'sports/categories/sports-socks');

        // Sports Shoes
        $sportsShoes = $this->firstOrCreateCategory($tenant, 'Sports Shoes', null, 'sports-shoes', 'sports/categories/sports-shoes');
        $this->firstOrCreateCategory($tenant, 'Football Shoes', $sportsShoes->id, 'football-shoes', 'sports/categories/football-shoes');
        $this->firstOrCreateCategory($tenant, 'Cricket Shoes', $sportsShoes->id, 'cricket-shoes', 'sports/categories/cricket-shoes');
        $this->firstOrCreateCategory($tenant, 'Running Shoes ', $sportsShoes->id, 'running-shoes-sports', 'sports/categories/running-shoes');
        $this->firstOrCreateCategory($tenant, 'Training Shoes', $sportsShoes->id, 'training-shoes', 'sports/categories/training-shoes');
        $this->firstOrCreateCategory($tenant, 'Basketball Shoes ', $sportsShoes->id, 'basketball-shoes-sports', 'sports/categories/basketball-shoes');
        $this->firstOrCreateCategory($tenant, 'Badminton Shoes ', $sportsShoes->id, 'badminton-shoes-sports', 'sports/categories/badminton-shoes');

        // Outdoor & Camping
        $outdoor = $this->firstOrCreateCategory($tenant, 'Outdoor & Camping', null, 'outdoor-camping', 'sports/categories/outdoor-camping');
        $this->firstOrCreateCategory($tenant, 'Camping Gear', $outdoor->id, 'camping-gear', 'sports/categories/camping-gear');
        $this->firstOrCreateCategory($tenant, 'Hiking Gear', $outdoor->id, 'hiking-gear', 'sports/categories/hiking-gear');
        $this->firstOrCreateCategory($tenant, 'Trekking Accessories', $outdoor->id, 'trekking-accessories', 'sports/categories/trekking-accessories');
        $this->firstOrCreateCategory($tenant, 'Outdoor Bags', $outdoor->id, 'outdoor-bags', 'sports/categories/outdoor-bags');
        $this->firstOrCreateCategory($tenant, 'Water Bottles', $outdoor->id, 'water-bottles', 'sports/categories/water-bottles');
        $this->firstOrCreateCategory($tenant, 'Outdoor Accessories', $outdoor->id, 'outdoor-accessories', 'sports/categories/outdoor-accessories');

        // Sports Accessories (top-level)
        $sportsAcc = $this->firstOrCreateCategory($tenant, 'Sports Accessories', null, 'sports-accessories', 'sports/categories/sports-accessories');
        $this->firstOrCreateCategory($tenant, 'Sports Bags', $sportsAcc->id, 'sports-bags', 'sports/categories/sports-bags');
        $this->firstOrCreateCategory($tenant, 'Water Bottles ', $sportsAcc->id, 'water-bottles-sports', 'sports/categories/water-bottles');
        $this->firstOrCreateCategory($tenant, 'Caps', $sportsAcc->id, 'caps', 'sports/categories/caps');
        $this->firstOrCreateCategory($tenant, 'Wristbands', $sportsAcc->id, 'wristbands', 'sports/categories/wristbands');
        $this->firstOrCreateCategory($tenant, 'Headbands', $sportsAcc->id, 'headbands', 'sports/categories/headbands');
        $this->firstOrCreateCategory($tenant, 'Sports Towels', $sportsAcc->id, 'sports-towels', 'sports/categories/sports-towels');
        $this->firstOrCreateCategory($tenant, 'Protective Gear', $sportsAcc->id, 'protective-gear', 'sports/categories/protective-gear');
    }

    private function seedBrands(Tenant $tenant): void
    {
        $brands = [
            'Adidas' => 'sports/brands/adidas',
            'Nike' => 'sports/brands/nike',
            'Puma' => 'sports/brands/puma',
            'Decathlon' => 'sports/brands/decathlon',
            'DSC' => 'sports/brands/dsc',
        ];
        foreach ($brands as $name => $stem) {
            $brand = $this->firstOrCreateBrand($tenant, $name);
            $this->ensureBrandLogo($brand, $stem);
        }
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, ['code' => 'size', 'label' => 'Size', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 1], ['S', 'M', 'L', 'XL', 'XXL']);
        $this->firstOrCreateAttribute($tenant, ['code' => 'sports_color', 'label' => 'Color', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Appearance', 'is_filterable' => true, 'is_variant_defining' => true, 'sort_order' => 2], ['Black', 'White', 'Red', 'Blue', 'Green', 'Yellow']);
        $this->firstOrCreateAttribute($tenant, ['code' => 'material', 'label' => 'Material', 'data_type' => AttributeDataType::Select, 'unit' => null, 'group' => 'Specifications', 'is_filterable' => true, 'is_variant_defining' => false, 'sort_order' => 3], ['Polyester', 'Cotton', 'Nylon', 'Rubber', 'Willow', 'Leather']);
    }

    private function seedCampaigns(Tenant $tenant): void
    {
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'sports-hot-deals'],
            [
                'name' => 'Sports Hot Deals',
                'description' => 'Up to 30% OFF on cricket, football and gym gear!',
                'accent_color' => '#16a34a',
                'short_tagline' => 'Up to 30% OFF',
                'status' => CampaignStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(7),
            ]
        );
        Campaign::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'new-arrivals-sports'],
            [
                'name' => 'New Arrivals',
                'description' => 'Just arrived - latest bats, rackets and shoes',
                'accent_color' => '#0ea5e9',
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
        $mainIds = Category::query()->where('tenant_id', $tenant->id)->whereNull('parent_id')->whereIn('slug', ['cricket', 'football', 'badminton', 'tennis', 'basketball', 'volleyball', 'table-tennis', 'fitness-gym', 'running', 'sportswear', 'sports-shoes', 'outdoor-camping', 'sports-accessories', 'sports-equipment'])->orderBy('id')->pluck('id')->all();
        $catConfig = ['source' => 'category', 'limit' => 12];
        if ($mainIds !== []) {
            $catConfig['category_ids'] = $mainIds;
        }
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Sport', 30, $catConfig);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Top Picks', 40, ['data_source' => 'featured', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Brand', 50, ['source' => 'brand', 'limit' => 5]);
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'sports-hot-deals')->first();
        $hot = $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Hot Deals - Sports Sale', 60, ['data_source' => 'campaign', 'limit' => 8]);
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
        $this->firstOrCreateBanner($tenant, 'Hero Banner 1', 'hero', 'sports/banners/hero-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Hero Banner 2', 'hero', 'sports/banners/hero-banner-2', 1);
        $this->firstOrCreateBanner($tenant, 'Promotional Banner 1', 'promo', 'sports/banners/promo-banner-1', 0);
        $this->firstOrCreateBanner($tenant, 'Flash Sale Banner', 'promo', 'sports/banners/flash-sale-banner', 1);
        $this->firstOrCreateBanner($tenant, 'Sports Hero', 'hero', 'sports/hero', 10);
    }

    private function seedProducts(Tenant $tenant): void
    {
        $cat = fn (string $name): ?Category => Category::query()->where('tenant_id', $tenant->id)->where('name', $name)->first();
        $brand = fn (string $name): ?Brand => Brand::query()->where('tenant_id', $tenant->id)->where('slug', Str::slug($name))->first();
        $brandAdidas = $brand('Adidas') ?? $brand('Nike');
        $brandNike = $brand('Nike') ?? $brandAdidas;

        $definitions = [
            [
                'model_number' => 'SPORT-CRICKET-BAT-ENG',
                'category_id' => $cat('Cricket Bats')?->id ?? $cat('Cricket')?->id,
                'brand_id' => $brand('DSC')?->id ?? $brandAdidas?->id,
                'base_price' => 550000, 'sold_count' => 42, 'is_featured' => true,
                'en' => ['name' => 'English Willow Cricket Bat', 'slug' => 'english-willow-cricket-bat', 'description' => 'Grade 1 English willow, perfect balance for power hitting.'],
                'asset' => 'sports/products/cricket-bat-english',
                'variants' => [['sku' => 'SPORT-CRICKET-BAT-ENG-BLK', 'price' => 550000, 'attrs' => ['sports_color' => 'Black'], 'qty' => '12.000']],
            ],
            [
                'model_number' => 'SPORT-CRICKET-BALL-LEA',
                'category_id' => $cat('Cricket Balls')?->id,
                'brand_id' => $brand('DSC')?->id,
                'base_price' => 85000, 'sold_count' => 65,
                'en' => ['name' => 'Leather Cricket Ball - Red', 'slug' => 'leather-cricket-ball-red', 'description' => 'Hand-stitched leather ball for match play.'],
                'asset' => 'sports/products/cricket-ball-leather',
                'variants' => [['sku' => 'SPORT-CRICKET-BALL-LEA-RED', 'price' => 85000, 'attrs' => ['sports_color' => 'Red'], 'qty' => '30.000']],
            ],
            [
                'model_number' => 'SPORT-FOOTBALL-ADI',
                'category_id' => $cat('Footballs')?->id ?? $cat('Football')?->id,
                'brand_id' => $brandAdidas?->id,
                'base_price' => 280000, 'sold_count' => 88, 'is_featured' => true,
                'en' => ['name' => 'Adidas Football - Match Ball', 'slug' => 'adidas-football-match-ball', 'description' => 'Official match football with butyl bladder.'],
                'asset' => 'sports/products/football-adidas',
                'variants' => [['sku' => 'SPORT-FOOTBALL-ADI-WHT', 'price' => 280000, 'attrs' => ['sports_color' => 'White'], 'qty' => '20.000']],
            ],
            [
                'model_number' => 'SPORT-BOOTS-NIKE',
                'category_id' => $cat('Football Boots')?->id,
                'brand_id' => $brandNike?->id,
                'base_price' => 750000, 'sold_count' => 35,
                'en' => ['name' => 'Nike Football Boots - FG', 'slug' => 'nike-football-boots-fg', 'description' => 'Firm ground boots with superior grip for football.'],
                'asset' => 'sports/products/football-boots-nike',
                'variants' => [['sku' => 'SPORT-BOOTS-NIKE-42-BLK', 'price' => 750000, 'attrs' => ['size' => 'L', 'sports_color' => 'Black'], 'qty' => '10.000']],
            ],
            [
                'model_number' => 'SPORT-BADMINTON-YONEX',
                'category_id' => $cat('Badminton Rackets')?->id,
                'brand_id' => $brand('Decathlon')?->id,
                'base_price' => 420000, 'sold_count' => 52, 'is_featured' => true,
                'en' => ['name' => 'Yonex Badminton Racket - Pro', 'slug' => 'yonex-badminton-racket-pro', 'description' => 'Lightweight graphite racket for professional play.'],
                'asset' => 'sports/products/badminton-racket-yonex',
                'variants' => [['sku' => 'SPORT-BADMINTON-YONEX-BLU', 'price' => 420000, 'attrs' => ['sports_color' => 'Blue'], 'qty' => '14.000']],
            ],
            [
                'model_number' => 'SPORT-TENNIS-WILSON',
                'category_id' => $cat('Tennis Rackets')?->id,
                'brand_id' => $brand('Decathlon')?->id,
                'base_price' => 650000, 'sold_count' => 28,
                'en' => ['name' => 'Wilson Tennis Racket - Tour', 'slug' => 'wilson-tennis-racket-tour', 'description' => 'Tour series racket with power and control.'],
                'asset' => 'sports/products/tennis-racket-wilson',
                'variants' => [['sku' => 'SPORT-TENNIS-WILSON-RED', 'price' => 650000, 'attrs' => ['sports_color' => 'Red'], 'qty' => '8.000']],
            ],
            [
                'model_number' => 'SPORT-BASKET-SPALDING',
                'category_id' => $cat('Basketballs')?->id,
                'brand_id' => $brand('Decathlon')?->id,
                'base_price' => 180000, 'sold_count' => 75, 'is_featured' => true,
                'en' => ['name' => 'Spalding Basketball - Size 7', 'slug' => 'spalding-basketball-size-7', 'description' => 'Official size 7 basketball for indoor/outdoor.'],
                'asset' => 'sports/products/basketball-spalding',
                'variants' => [['sku' => 'SPORT-BASKET-SPALDING-ORG', 'price' => 180000, 'attrs' => ['sports_color' => 'Black'], 'qty' => '22.000']],
            ],
            [
                'model_number' => 'SPORT-VOLLEY-MIKASA',
                'category_id' => $cat('Volleyballs')?->id,
                'brand_id' => $brand('Decathlon')?->id,
                'base_price' => 220000, 'sold_count' => 30,
                'en' => ['name' => 'Mikasa Volleyball - Match', 'slug' => 'mikasa-volleyball-match', 'description' => 'Match volleyball with micro-fiber cover.'],
                'asset' => 'sports/products/volleyball-mikasa',
                'variants' => [['sku' => 'SPORT-VOLLEY-MIKASA-WHT', 'price' => 220000, 'attrs' => ['sports_color' => 'White'], 'qty' => '12.000']],
            ],
            [
                'model_number' => 'SPORT-TT-BAT',
                'category_id' => $cat('Table Tennis Bats')?->id,
                'brand_id' => $brandAdidas?->id,
                'base_price' => 95000, 'sold_count' => 48,
                'en' => ['name' => 'Table Tennis Bat - Pro', 'slug' => 'table-tennis-bat-pro', 'description' => 'Professional TT bat with control and spin.'],
                'asset' => 'sports/products/table-tennis-bat',
                'variants' => [['sku' => 'SPORT-TT-BAT-RED', 'price' => 95000, 'attrs' => ['sports_color' => 'Red'], 'qty' => '18.000']],
            ],
            [
                'model_number' => 'SPORT-DUMBBELLS-10KG',
                'category_id' => $cat('Dumbbells')?->id,
                'brand_id' => $brandAdidas?->id,
                'base_price' => 450000, 'sold_count' => 38, 'is_featured' => true,
                'en' => ['name' => 'Dumbbells 10KG Pair - Rubber Coated', 'slug' => 'dumbbells-10kg-pair-rubber-coated', 'description' => '10KG pair dumbbells for home gym, rubber coated.'],
                'asset' => 'sports/products/dumbbells-10kg',
                'variants' => [['sku' => 'SPORT-DUMBBELLS-10KG-BLK', 'price' => 450000, 'attrs' => ['sports_color' => 'Black'], 'qty' => '10.000']],
            ],
            [
                'model_number' => 'SPORT-YOGA-MAT-PRO',
                'category_id' => $cat('Yoga Mats')?->id,
                'brand_id' => $brandAdidas?->id,
                'base_price' => 95000, 'sold_count' => 62, 'is_featured' => true,
                'en' => ['name' => 'Yoga Mat Pro - 6mm', 'slug' => 'yoga-mat-pro-6mm', 'description' => 'Non-slip 6mm yoga mat for gym and yoga.'],
                'asset' => 'sports/products/yoga-mat-pro',
                'variants' => [['sku' => 'SPORT-YOGA-MAT-PRO-BLU', 'price' => 95000, 'attrs' => ['sports_color' => 'Blue'], 'qty' => '15.000']],
            ],
            [
                'model_number' => 'SPORT-RUNNING-SHOES',
                'category_id' => $cat('Running Shoes')?->id,
                'brand_id' => $brandNike?->id,
                'base_price' => 650000, 'sold_count' => 55, 'is_featured' => true,
                'en' => ['name' => 'Nike Running Shoes - Air', 'slug' => 'nike-running-shoes-air', 'description' => 'Lightweight running shoes with air cushion.'],
                'asset' => 'sports/products/running-shoes-nike',
                'variants' => [['sku' => 'SPORT-RUNNING-SHOES-42-BLK', 'price' => 650000, 'attrs' => ['size' => 'L', 'sports_color' => 'Black'], 'qty' => '12.000']],
            ],
            [
                'model_number' => 'SPORT-JERSEY-BD',
                'category_id' => $cat('Jerseys')?->id,
                'brand_id' => $brandNike?->id,
                'base_price' => 180000, 'sold_count' => 95, 'is_featured' => true,
                'en' => ['name' => 'Bangladesh Cricket Jersey', 'slug' => 'bangladesh-cricket-jersey', 'description' => 'Official Bangladesh jersey, breathable fabric.'],
                'asset' => 'sports/products/jersey-bangladesh',
                'variants' => [['sku' => 'SPORT-JERSEY-BD-L-GRN', 'price' => 180000, 'attrs' => ['size' => 'L', 'sports_color' => 'Green'], 'qty' => '20.000']],
            ],
            [
                'model_number' => 'SPORT-SHOES-PUMA',
                'category_id' => $cat('Training Shoes')?->id ?? $cat('Sports Shoes')?->id,
                'brand_id' => $brand('Puma')?->id,
                'base_price' => 580000, 'sold_count' => 40,
                'en' => ['name' => 'Puma Training Shoes', 'slug' => 'puma-training-shoes', 'description' => 'Training shoes for gym and sports.'],
                'asset' => 'sports/products/sports-shoes-puma',
                'variants' => [['sku' => 'SPORT-SHOES-PUMA-42-WHT', 'price' => 580000, 'attrs' => ['size' => 'L', 'sports_color' => 'White'], 'qty' => '9.000']],
            ],
            [
                'model_number' => 'SPORT-CAMPING-TENT',
                'category_id' => $cat('Camping Gear')?->id,
                'brand_id' => $brand('Decathlon')?->id,
                'base_price' => 1200000, 'sold_count' => 12,
                'en' => ['name' => 'Camping Tent - 4 Person', 'slug' => 'camping-tent-4-person', 'description' => 'Waterproof 4-person camping tent.'],
                'asset' => 'sports/products/camping-tent',
                'variants' => [['sku' => 'SPORT-CAMPING-TENT-GRN', 'price' => 1200000, 'attrs' => ['sports_color' => 'Green'], 'qty' => '5.000']],
            ],
            [
                'model_number' => 'SPORT-BAG-ADIDAS',
                'category_id' => $cat('Sports Bags')?->id,
                'brand_id' => $brandAdidas?->id,
                'base_price' => 180000, 'sold_count' => 28,
                'en' => ['name' => 'Adidas Sports Bag - Duffel', 'slug' => 'adidas-sports-bag-duffel', 'description' => 'Large duffel bag for sports gear.'],
                'asset' => 'sports/products/sports-bag-adidas',
                'variants' => [['sku' => 'SPORT-BAG-ADIDAS-BLK', 'price' => 180000, 'attrs' => ['sports_color' => 'Black'], 'qty' => '14.000']],
            ],
        ];

        $legacy = [
            [
                'model_number' => 'SPORT-SHOE-001',
                'category_id' => $cat('Footwear')?->id,
                'brand_id' => $brandAdidas?->id,
                'base_price' => 650000, 'is_featured' => true, 'sold_count' => 32,
                'en' => ['name' => 'Pro Running Shoes', 'slug' => 'pro-running-shoes', 'description' => 'Lightweight running shoes with cushioned sole.'],
                'asset' => 'sports/product-1',
                'variants' => [['sku' => 'SPORT-SHOE-001-M-BLK', 'price' => 650000, 'attrs' => ['size' => 'M', 'sports_color' => 'Black'], 'qty' => '14.000']],
            ],
            [
                'model_number' => 'SPORT-TSHIRT-002',
                'category_id' => $cat('Apparel')?->id,
                'brand_id' => $brand('Nike')?->id,
                'base_price' => 180000, 'is_featured' => true, 'sold_count' => 28,
                'en' => ['name' => 'Training T-Shirt', 'slug' => 'training-t-shirt', 'description' => 'Breathable polyester training t-shirt.'],
                'asset' => 'sports/product-2',
                'variants' => [['sku' => 'SPORT-TSHIRT-002-L-BLU', 'price' => 180000, 'attrs' => ['size' => 'L', 'sports_color' => 'Blue'], 'qty' => '20.000']],
            ],
            [
                'model_number' => 'SPORT-MAT-003',
                'category_id' => $cat('Sports Equipment')?->id,
                'brand_id' => $brandAdidas?->id,
                'base_price' => 95000, 'is_featured' => true, 'sold_count' => 18,
                'en' => ['name' => 'Yoga Mat Pro', 'slug' => 'yoga-mat-pro', 'description' => 'Non-slip yoga mat with 6mm thickness.'],
                'asset' => 'sports/product-3',
                'variants' => [['sku' => 'SPORT-MAT-003-STD', 'price' => 95000, 'attrs' => ['sports_color' => 'Black'], 'qty' => '10.000']],
            ],
        ];

        $all = array_merge($definitions, $legacy);
        $campaign = Campaign::query()->where('tenant_id', $tenant->id)->where('slug', 'sports-hot-deals')->first();
        $hotSkus = ['SPORT-CRICKET-BAT-ENG-BLK', 'SPORT-FOOTBALL-ADI-WHT', 'SPORT-BADMINTON-YONEX-BLU', 'SPORT-RUNNING-SHOES-42-BLK'];

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
                    $inflated = (int) round((int) $variant->price * 1.30);
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
        $cricket = $mk('Cricket', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Cricket') ?? '/', 20);
        $football = $mk('Football', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Football') ?? '/', 30);
        $badminton = $mk('Badminton', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Badminton') ?? '/', 40);
        $fitness = $mk('Fitness & Gym', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Fitness & Gym') ?? '/', 50);
        $brands = $mk('Brands', LinkType::External->value, '/brands', 60);
        $mk('Blog', LinkType::BlogIndex->value, null, 65);
        $mk('Sale 🔥', LinkType::External->value, '/offers', 70, null, '🔥');

        // Cricket dropdown
        $mk('Cricket Bats', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Cricket Bats') ?? '/', 21, $cricket->id);
        $mk('Cricket Balls', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Cricket Balls') ?? '/', 22, $cricket->id);
        $mk('Cricket Protective Gear', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Cricket Protective Gear') ?? '/', 23, $cricket->id);
        $mk('Cricket Bags', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Cricket Bags') ?? '/', 24, $cricket->id);

        // Football
        $mk('Footballs', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Footballs') ?? '/', 31, $football->id);
        $mk('Football Boots', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Football Boots') ?? '/', 32, $football->id);
        $mk('Football Jerseys', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Football Jerseys') ?? '/', 33, $football->id);

        // Badminton
        $mk('Badminton Rackets', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Badminton Rackets') ?? '/', 41, $badminton->id);
        $mk('Shuttlecocks', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Shuttlecocks') ?? '/', 42, $badminton->id);

        // Fitness
        $mk('Dumbbells', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Dumbbells') ?? '/', 51, $fitness->id);
        $mk('Yoga Mats', LinkType::Category->value, $this->categorySlugForMenu($tenant, 'Yoga Mats') ?? '/', 52, $fitness->id);

        // Brands
        foreach (['Adidas', 'Nike', 'Puma', 'Decathlon'] as $idx => $bName) {
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
            ['tenant_id' => $tenant->id, 'slug' => 'main-outlet-gulshan-sports'],
            [
                'name' => 'Main Outlet - Gulshan (Sports)',
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
            $src = $this->resolveAssetPath('sports/categories/cricket');
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
                'sports/categories/'.$stem,
                'sports/products/'.$stem,
                'sports/brands/'.$stem,
                'sports/banners/'.$stem,
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
