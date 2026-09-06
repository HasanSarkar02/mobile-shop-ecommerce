<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\IndustrySeeders\ElectronicsIndustrySeeder;
use App\Services\IndustrySeeders\FashionIndustrySeeder;
use App\Services\IndustrySeeders\FurnitureIndustrySeeder;
use App\Services\IndustrySeeders\GeneralIndustrySeeder;
use App\Services\IndustrySeeders\GroceryIndustrySeeder;
use App\Services\IndustrySeeders\IndustrySeederService;
use App\Services\IndustrySeeders\SportsIndustrySeeder;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillStarterMedia extends Command
{
    protected $signature = 'tenants:backfill-starter-media {--tenant= : Subdomain or ID of single tenant} {--force : Force replace even non-placeholder images}';

    protected $description = 'Backfill starter-asset media for existing tenants (categories, brands, products, banners) handling jpg/jpeg/png/webp any case';

    public function handle(): int
    {
        $filter = $this->option('tenant');
        $force = (bool) $this->option('force');

        $query = Tenant::query();
        if ($filter) {
            $query->where(function ($q) use ($filter) {
                $q->where('subdomain', $filter)->orWhere('id', $filter);
            });
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->error('No tenants matched.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant {$tenant->subdomain} ({$tenant->id}) industry={$tenant->industryCode()}");
            $previous = tenant();
            app(Tenancy::class)->set($tenant);
            try {
                $this->backfillTenant($tenant, $force);
            } finally {
                app(Tenancy::class)->set($previous);
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function backfillTenant(Tenant $tenant, bool $force): void
    {
        $industry = $tenant->industryCode();
        $seederClass = match ($industry) {
            'electronics', 'mobile' => ElectronicsIndustrySeeder::class,
            'grocery' => GroceryIndustrySeeder::class,
            'fashion' => FashionIndustrySeeder::class,
            'general' => GeneralIndustrySeeder::class,
            'sports' => SportsIndustrySeeder::class,
            'furniture' => FurnitureIndustrySeeder::class,
            default => null,
        };

        if ($seederClass === null) {
            $this->warn("  Skipping unknown industry {$industry}");

            return;
        }

        $seeder = app($seederClass);
        $supportsMediaBackfill = method_exists($seeder, 'ensureCategoryImage') && method_exists($seeder, 'ensureBrandLogo');

        if ($supportsMediaBackfill) {
            $categories = Category::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get();
            foreach ($categories as $cat) {
                $stem = $this->guessCategoryStem($cat, $industry);
                if ($stem) {
                    $this->callPrivate($seeder, 'ensureCategoryImage', [$cat, $stem, $force]);
                }
            }

            $brands = Brand::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get();
            foreach ($brands as $brand) {
                $stem = $industry.'/brands/'.strtolower($brand->slug);
                $this->callPrivate($seeder, 'ensureBrandLogo', [$brand, $stem, $force]);
            }

            $banners = Banner::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get();
            foreach ($banners as $banner) {
                if ($banner->getFirstMediaUrl('image') !== '' && ! $force) {
                    $media = $banner->getFirstMedia('image');
                    if ($media && $media->size > 50000) {
                        continue;
                    }
                }
                $stem = $this->guessBannerStem($banner, $industry);
                if (! $stem) {
                    continue;
                }
                $path = $this->callPrivate($seeder, 'resolveAssetPath', [$stem]);
                if ($path && is_file($path)) {
                    if ($banner->getFirstMediaUrl('image') !== '') {
                        $banner->clearMediaCollection('image');
                    }
                    try {
                        $banner->addMedia($path)->preservingOriginal()->toMediaCollection('image');
                        $this->line("  Banner {$banner->title} → {$path}");
                    } catch (\Throwable $e) {
                        $this->warn("  Banner {$banner->title} failed: ".$e->getMessage());
                    }
                }
            }

            $products = Product::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get();
            foreach ($products as $product) {
                $hasMedia = $product->getFirstMediaUrl('images') !== '';
                $needs = ! $hasMedia || $force;
                if (! $needs && $hasMedia) {
                    $media = $product->getFirstMedia('images');
                    if ($media && $media->size < 50000) {
                        $needs = true;
                    }
                }
                if ($needs) {
                    $stem = $this->guessProductStem($product, $industry);
                    $path = $stem ? $this->callPrivate($seeder, 'resolveAssetPath', [$stem]) : null;
                    if ($path && is_file($path)) {
                        if ($hasMedia) {
                            $product->clearMediaCollection('images');
                        }
                        try {
                            $product->addMedia($path)->preservingOriginal()->toMediaCollection('images');
                            $this->line("  Product {$product->model_number} → {$path}");
                        } catch (\Throwable $e) {
                            $this->warn("  Product {$product->model_number} failed: ".$e->getMessage());
                        }
                    }
                }

                $variants = ProductVariant::withoutGlobalScope('tenant')->where('product_id', $product->id)->get();
                foreach ($variants as $variant) {
                    $vHas = $variant->getFirstMediaUrl('images') !== '';
                    $vNeeds = ! $vHas || $force;
                    if (! $vNeeds && $vHas) {
                        $media = $variant->getFirstMedia('images');
                        if ($media && $media->size < 50000) {
                            $vNeeds = true;
                        }
                    }
                    if ($vNeeds) {
                        $stem = $this->guessProductStem($product, $industry);
                        $path = $stem ? $this->callPrivate($seeder, 'resolveAssetPath', [$stem]) : null;
                        if ($path && is_file($path)) {
                            if ($vHas) {
                                $variant->clearMediaCollection('images');
                            }
                            try {
                                $variant->addMedia($path)->preservingOriginal()->toMediaCollection('images');
                            } catch (\Throwable $e) {
                            }
                        }
                    }
                }
            }
        }

        try {
            app(IndustrySeederService::class)->seed($tenant);
        } catch (\Throwable $e) {
            $this->warn('  Seeder re-run skipped due to existing data: '.$e->getMessage());
        }

        $link = public_path('storage');
        if (! is_link($link) && ! is_dir($link)) {
            $this->warn('  public/storage link missing — run php artisan storage:link');
        }
    }

    private function guessCategoryStem(Category $cat, string $industry): ?string
    {
        $slug = $cat->slug;

        return $industry.'/categories/'.$slug;
    }

    private function guessBannerStem(Banner $banner, string $industry): ?string
    {
        $map = [
            'Hero Banner 1' => $industry.'/banners/hero-banner-1',
            'Hero Banner 2' => $industry.'/banners/hero-banner-2',
            'Promotional Banner 1' => $industry.'/banners/promo-banner-1',
            'Flash Sale Banner' => $industry.'/banners/flash-sale-banner',
            'Electronics Hero' => 'electronics/hero',
            'Grocery Hero' => 'grocery/hero',
            'Fashion Hero' => 'fashion/hero',
        ];

        return $map[$banner->title] ?? $industry.'/banners/'.Str::slug($banner->title);
    }

    private function guessProductStem(Product $product, string $industry): ?string
    {
        $slug = ProductTranslation::where('product_id', $product->id)->where('locale', 'en')->value('slug');
        if ($slug) {
            $dir = base_path('resources/starter-assets/'.$industry.'/products');
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $f) {
                    $candidateBase = pathinfo($f, PATHINFO_FILENAME);
                    if (str_contains(strtolower($slug), strtolower($candidateBase)) || str_contains(strtolower($candidateBase), strtolower(explode('-', $slug)[0] ?? ''))) {
                        return $industry.'/products/'.$candidateBase;
                    }
                }
            }

            return $industry.'/products/'.explode('-', $slug)[0].'-'.explode('-', $slug)[1] ?? $slug;
        }

        return $industry.'/products/'.strtolower($product->model_number);
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function callPrivate(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($obj, $args);
    }
}
