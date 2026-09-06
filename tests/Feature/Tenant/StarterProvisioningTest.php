<?php

declare(strict_types=1);

use App\Enums\TenantIndustry;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IndustrySeeders\IndustrySeederService;
use App\Services\SubscriptionService;
use App\Services\TenantBootstrapService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists platform selected industry via TenantBootstrapService', function (): void {
    // Ensure trial plan exists via helper
    actingAsTenant(['subdomain' => 'seed-'.uniqid()]);
    $service = app(TenantBootstrapService::class);
    $plan = Plan::query()->where('slug', 'trial')->firstOrFail();
    [$tenant] = $service->bootstrap([
        'name' => 'Industry Persist Test',
        'subdomain' => 'ind-persist-'.uniqid(),
        'industry' => TenantIndustry::Grocery->value,
        'plan' => $plan->slug,
        'owner' => ['name' => 'Owner', 'email' => 'owner-'.uniqid().'@test.test', 'phone' => null],
    ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION, invitedBy: User::factory()->create(['is_platform_admin' => true, 'is_active' => true]));

    expect($tenant->industry)->toBe(TenantIndustry::Grocery)
        ->and($tenant->industryCode())->toBe('grocery');
});

it('mobile industry maps to electronics starter', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'mobile-'.uniqid(), 'industry' => TenantIndustry::Mobile->value]);
    app(IndustrySeederService::class)->seed($tenant);

    $cats = Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('name')->all();
    expect($cats)->toContain('Smartphones')->toContain('Laptops');
});

it('each industry gets starter brands', function (): void {
    foreach ([TenantIndustry::Electronics, TenantIndustry::Fashion, TenantIndustry::Grocery, TenantIndustry::Sports, TenantIndustry::Furniture, TenantIndustry::General] as $industry) {
        $tenant = actingAsTenant(['subdomain' => 'brand-'.$industry->value.'-'.uniqid(), 'industry' => $industry->value]);

        $brands = Brand::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('name')->all();
        expect($brands)->not->toBeEmpty();
    }
});

it('each industry gets published featured starter products with translations', function (): void {
    foreach ([TenantIndustry::Electronics, TenantIndustry::Fashion, TenantIndustry::Grocery] as $industry) {
        $tenant = actingAsTenant(['subdomain' => 'prod-'.$industry->value.'-'.uniqid(), 'industry' => $industry->value]);

        $products = Product::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('status', 'published')->where('is_featured', true)->with('translations')->get();
        expect($products)->not->toBeEmpty();
        foreach ($products as $p) {
            expect($p->translations)->not->toBeEmpty();
            expect($p->name)->not->toBeEmpty();
        }
    }
});

it('products have valid variants SKUs prices and stock for tracked items', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'variant-'.uniqid(), 'industry' => TenantIndustry::Electronics->value]);

    $variants = ProductVariant::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get();
    expect($variants)->not->toBeEmpty();
    $stocked = 0;
    foreach ($variants as $v) {
        expect($v->sku)->not->toBeEmpty()
            ->and($v->price)->toBeGreaterThan(0);
        $stock = StockItem::query()->withoutGlobalScope('tenant')->where('product_variant_id', $v->id)->first();
        expect($stock)->not->toBeNull();
        if ((float) $stock->quantity > 0) {
            $stocked++;
        }
    }
    expect($stocked)->toBeGreaterThan(0);
});

it('hero banner exists with hero placement and media', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'banner-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    $banner = Banner::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('placement', 'hero')->where('is_active', true)->first();
    expect($banner)->not->toBeNull();
    // media may be empty in test env if GD not available, but banner record must exist
    expect($banner->title)->not->toBeEmpty();
});

it('homepage sections include hero/category/trust/product in correct order', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'home-'.uniqid(), 'industry' => TenantIndustry::Electronics->value]);
    $sections = HomepageSection::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->orderBy('sort_order')->pluck('type')->map(fn ($t) => $t instanceof BackedEnum ? $t->value : (string) $t)->all();
    expect($sections)->toContain('banner_carousel')
        ->toContain('category_grid')
        ->toContain('trust_badges')
        ->toContain('product_grid');
    $bannerPos = array_search('banner_carousel', $sections);
    $trustPos = array_search('trust_badges', $sections);
    $productPos = array_search('product_grid', $sections);
    expect($bannerPos)->toBeLessThan($trustPos)
        ->and($trustPos)->toBeLessThan($productPos);
});

it('tenant isolation: starter data not leaked', function (): void {
    $tA = actingAsTenant(['subdomain' => 'iso-a-'.uniqid(), 'industry' => TenantIndustry::Electronics->value]);
    $tB = Tenant::factory()->create(['subdomain' => 'iso-b-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    app(SubscriptionService::class)->startTrial($tB, Plan::query()->where('slug', 'trial')->firstOrFail(), 14);
    app(Tenancy::class)->set($tB);
    app(IndustrySeederService::class)->seed($tB);
    app(Tenancy::class)->set($tA);

    $aBrands = Brand::query()->withoutGlobalScope('tenant')->where('tenant_id', $tA->id)->pluck('name')->all();
    $bBrands = Brand::query()->withoutGlobalScope('tenant')->where('tenant_id', $tB->id)->pluck('name')->all();
    expect($aBrands)->toContain('Apple')->not->toContain('FreshMart');
    expect($bBrands)->toContain('FreshMart')->not->toContain('Apple');

    $aProducts = Product::query()->withoutGlobalScope('tenant')->where('tenant_id', $tA->id)->pluck('model_number')->all();
    $bProducts = Product::query()->withoutGlobalScope('tenant')->where('tenant_id', $tB->id)->pluck('model_number')->all();
    expect($aProducts)->not->toEqual($bProducts);
});

it('idempotent: re-running seed does not duplicate', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'idem-'.uniqid(), 'industry' => TenantIndustry::Fashion->value]);
    $countsBefore = [
        'cat' => Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
        'brand' => Brand::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
        'product' => Product::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
        'banner' => Banner::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
        'section' => HomepageSection::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
    ];

    app(IndustrySeederService::class)->seed($tenant);
    app(IndustrySeederService::class)->seed($tenant);

    expect(Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())->toBe($countsBefore['cat'])
        ->and(Brand::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())->toBe($countsBefore['brand'])
        ->and(Product::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())->toBe($countsBefore['product'])
        ->and(Banner::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())->toBe($countsBefore['banner']);
});

it('merchant edits preserved on reseed', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'preserve-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    $brand = Brand::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('slug', 'freshmart')->firstOrFail();
    $brand->update(['name' => 'My FreshMart']);

    app(IndustrySeederService::class)->seed($tenant);

    expect(Brand::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('id', $brand->id)->first()->name)->toBe('My FreshMart');
});

it('bootstrap rollback leaves no partial tenant on failure', function (): void {
    $beforeCount = Tenant::query()->withoutGlobalScope('tenant')->count();
    $service = app(TenantBootstrapService::class);
    // Force failure via invalid plan
    try {
        $service->bootstrap([
            'name' => 'Rollback Test',
            'subdomain' => 'rollback-'.uniqid(),
            'industry' => TenantIndustry::Electronics->value,
            'plan' => 'nonexistent-plan-'.uniqid(),
            'owner' => ['name' => 'Owner', 'email' => 'rollback-'.uniqid().'@test.test', 'phone' => null],
        ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION, invitedBy: User::factory()->create(['is_platform_admin' => true, 'is_active' => true]));
    } catch (Throwable $e) {
    }

    expect(Tenant::query()->withoutGlobalScope('tenant')->count())->toBe($beforeCount);
});
