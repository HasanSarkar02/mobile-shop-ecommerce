<?php

declare(strict_types=1);

use App\Enums\TenantIndustry;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Support\IndustryConfig;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('falls back to industry preset when product title empty', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'polish-empty-title', 'industry' => TenantIndustry::Grocery->value]);
    app(Tenancy::class)->set($tenant);
    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'product_grid',
        'title' => '',
        'config' => [],
        'is_active' => true,
        'sort_order' => 3,
    ]);
    $product = Product::factory()->create(['status' => 'published', 'is_featured' => true]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['price' => 10000, 'is_active' => true]);

    $html = get('http://polish-empty-title.'.config('tenancy.central_domain').'/')->assertOk()->getContent();
    expect($html)->toContain('Popular Picks');
});

it('uses tenant title when non-empty', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'polish-custom-title', 'industry' => TenantIndustry::Grocery->value]);
    app(Tenancy::class)->set($tenant);
    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'product_grid',
        'title' => 'Best Sellers',
        'config' => [],
        'is_active' => true,
        'sort_order' => 3,
    ]);
    $product = Product::factory()->create(['status' => 'published', 'is_featured' => true]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['price' => 10000, 'is_active' => true]);

    $html = get('http://polish-custom-title.'.config('tenancy.central_domain').'/')->assertOk()->getContent();
    expect($html)->toContain('Best Sellers')
        ->and($html)->not->toContain('Popular Picks');
});

it('unknown industry falls back to general Featured Products', function (): void {
    expect(IndustryConfig::get('unknown_vertical', 'homepage.product_grid_title', 'fallback'))->toBe('Featured Products')
        ->and(IndustryConfig::get('general', 'homepage.product_grid_title'))->toBe('Featured Products')
        ->and(IndustryConfig::get('mobile', 'homepage.product_grid_title'))->toBe('Featured Smartphones')
        ->and(IndustryConfig::get('grocery', 'homepage.product_grid_title'))->toBe('Popular Picks')
        ->and(IndustryConfig::get('fashion', 'homepage.product_grid_title'))->toBe('Trending Now');
});

it('homepage layout uses subtle #FAFAFA background and product cards remain white', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'polish-bg']);
    $html = get('http://polish-bg.'.config('tenancy.central_domain').'/')->assertOk()->getContent();
    expect($html)->toContain('bg-[#FAFAFA]');
    // product card component still bg-white
    $cardView = file_get_contents(resource_path('views/components/storefront/product-cards/default.blade.php'));
    expect($cardView)->toContain('bg-white');
});

it('product-grid has surgical negative margin and heading retains text-xl font-bold', function (): void {
    $view = file_get_contents(resource_path('views/storefront/partials/sections/product-grid.blade.php'));
    expect($view)->toContain('-mt-4')
        ->and($view)->toContain('sm:-mt-2')
        ->and($view)->toContain('text-xl font-bold')
        ->and($view)->toContain('w-8 h-8')
        ->and($view)->toContain('View all');
});
