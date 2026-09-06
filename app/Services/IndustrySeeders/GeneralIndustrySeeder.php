<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\LinkType;
use App\Enums\MenuLocation;
use App\Enums\ProductStatus;
use App\Enums\Visibility;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Support\ThemePresets;
use Illuminate\Support\Str;

final class GeneralIndustrySeeder
{
    public function seed(Tenant $tenant): void
    {
        $generalCat = $this->firstOrCreateCategory($tenant, 'General');
        $brandEssentials = $this->firstOrCreateBrand($tenant, 'Essentials');
        $brandHomeBase = $this->firstOrCreateBrand($tenant, 'HomeBase');

        $this->seedHomepage($tenant);
        $this->firstOrCreateBanner($tenant, 'General Hero', 'hero', 'general/hero.jpg');

        $this->seedProducts($tenant, $generalCat, $brandEssentials, $brandHomeBase);

        $this->seedMenus($tenant);
        $this->seedTheme($tenant);
    }

    private function seedHomepage(Tenant $tenant): void
    {
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Hero Banner', 10, ['placement' => 'hero']);
        $this->firstOrCreateHomepageSection($tenant, 'trust_badges', 'Our Promise', 20);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Featured Products', 40, ['data_source' => 'featured', 'limit' => 8]);
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Promotional Banner', 90, ['placement' => 'promo']);
        $this->firstOrCreateHomepageSection($tenant, 'blog_grid', 'From the Blog', 100, ['limit' => 3]);
        $this->firstOrCreateHomepageSection($tenant, 'newsletter_cta', 'Stay Updated', 110);
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

        $mk('Home', LinkType::External->value, '/', 10);
        $brands = $mk('Brands', LinkType::External->value, '/brands', 40);
        $mk('Blog', LinkType::BlogIndex->value, null, 45);
        $mk('Offers', LinkType::External->value, '/offers', 50, null, '🔥');

        foreach (['Essentials', 'HomeBase'] as $idx => $bName) {
            $mk($bName, LinkType::Brand->value, Str::slug($bName), 41 + $idx, $brands->id);
        }
    }

    private function seedProducts(Tenant $tenant, Category $cat, Brand $b1, Brand $b2): void
    {
        $p1 = $this->firstOrCreateProduct($tenant, [
            'model_number' => 'GEN-BP-001',
            'category_id' => $cat->id,
            'brand_id' => $b1->id,
            'base_price' => 250000,
            'is_featured' => true,
        ], [
            'en' => ['name' => 'Classic Canvas Backpack', 'slug' => 'classic-canvas-backpack', 'description' => 'Durable canvas backpack with multiple compartments for daily use.'],
        ], 'general/product-1.jpg');

        $this->firstOrCreateVariant($tenant, $p1, 'GEN-BP-001-STD', 250000, 'general/product-1.jpg');
        $this->ensureStock($tenant, $p1->variants()->where('sku', 'GEN-BP-001-STD')->firstOrFail(), '5.000');

        $p2 = $this->firstOrCreateProduct($tenant, [
            'model_number' => 'GEN-WB-002',
            'category_id' => $cat->id,
            'brand_id' => $b2->id,
            'base_price' => 120000,
            'is_featured' => true,
        ], [
            'en' => ['name' => 'Stainless Steel Water Bottle', 'slug' => 'stainless-steel-water-bottle', 'description' => 'Double-walled insulated bottle keeps drinks cold for 24h or hot for 12h.'],
        ], 'general/product-2.jpg');

        $this->firstOrCreateVariant($tenant, $p2, 'GEN-WB-002-500ML', 120000, 'general/product-2.jpg');
        $this->ensureStock($tenant, $p2->variants()->where('sku', 'GEN-WB-002-500ML')->firstOrFail(), '10.000');

        $p3 = $this->firstOrCreateProduct($tenant, [
            'model_number' => 'GEN-DO-003',
            'category_id' => $cat->id,
            'brand_id' => $b2->id,
            'base_price' => 85000,
            'is_featured' => true,
        ], [
            'en' => ['name' => 'Desk Organizer Set', 'slug' => 'desk-organizer-set', 'description' => 'Minimal wooden desk organizer with pen holder and drawer.'],
        ], 'general/product-3.jpg');

        $this->firstOrCreateVariant($tenant, $p3, 'GEN-DO-003-STD', 85000, 'general/product-3.jpg');
        $this->ensureStock($tenant, $p3->variants()->where('sku', 'GEN-DO-003-STD')->firstOrFail(), '8.000');
    }

    private function seedTheme(Tenant $tenant): void
    {
        $preset = ThemePresets::forIndustry('general');

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

    private function firstOrCreateCategory(Tenant $tenant, string $name, ?int $parentId = null): Category
    {
        return Category::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name, 'parent_id' => $parentId],
            ['slug' => Str::slug($name)]
        );
    }

    private function firstOrCreateBrand(Tenant $tenant, string $name): Brand
    {
        return Brand::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => Str::slug($name)],
            ['name' => $name]
        );
    }

    private function firstOrCreateHomepageSection(Tenant $tenant, string $type, string $title, int $sortOrder, array $config = []): HomepageSection
    {
        return HomepageSection::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'type' => $type, 'title' => $title],
            [
                'config' => $config,
                'visibility' => Visibility::All,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function firstOrCreateBanner(Tenant $tenant, string $title, string $placement, string $asset): Banner
    {
        $banner = Banner::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => $title, 'placement' => $placement],
            [
                'media_type' => 'image',
                'visibility' => Visibility::All,
                'link_type' => 'none',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        $path = base_path('resources/starter-assets/'.$asset);
        if (is_file($path) && $banner->getFirstMediaUrl('image') === '') {
            try {
                $banner->addMedia($path)->preservingOriginal()->toMediaCollection('image');
            } catch (\Throwable $e) {
            }
        }

        return $banner;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  array<string, array{ name: string, slug: string, description?: string }>  $translations
     */
    private function firstOrCreateProduct(Tenant $tenant, array $attrs, array $translations, ?string $asset = null): Product
    {
        $product = Product::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'model_number' => $attrs['model_number']],
            array_merge([
                'status' => ProductStatus::Published,
                'type' => 'simple',
                'published_at' => now(),
            ], $attrs)
        );

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

        if ($asset !== null) {
            $path = base_path('resources/starter-assets/'.$asset);
            if (is_file($path) && $product->getFirstMediaUrl('images') === '') {
                try {
                    $product->addMedia($path)->preservingOriginal()->toMediaCollection('images');
                } catch (\Throwable $e) {
                }
            }
        }

        return $product;
    }

    private function firstOrCreateVariant(Tenant $tenant, Product $product, string $sku, int $price, ?string $asset = null): ProductVariant
    {
        $variant = ProductVariant::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => $sku],
            [
                'product_id' => $product->id,
                'price' => $price,
                'is_active' => true,
            ]
        );

        if ($asset !== null) {
            $path = base_path('resources/starter-assets/'.$asset);
            if (is_file($path) && $variant->getFirstMediaUrl('images') === '') {
                try {
                    $variant->addMedia($path)->preservingOriginal()->toMediaCollection('images');
                } catch (\Throwable $e) {
                }
            }
        }

        return $variant;
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
}
