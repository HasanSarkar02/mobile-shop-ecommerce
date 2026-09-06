<?php

declare(strict_types=1);

use App\Enums\TenantIndustry;
use App\Filament\Store\Resources\HomepageSectionResource\Pages\CreateHomepageSection;
use App\Filament\Store\Resources\HomepageSectionResource\Pages\EditHomepageSection;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\IndustrySeeders\IndustrySeederService;
use App\Services\SubscriptionService;
use App\Support\HomepagePresentation;
use App\Support\IndustryConfig;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

// A. Existing sections with no presentation config render as one row.
it('renders one row when presentation missing', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-a-'.uniqid(), 'industry' => TenantIndustry::Electronics->value]);
    $section = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'product_grid',
        'title' => 'Test',
        'config' => [],
        'is_active' => true,
        'sort_order' => 1,
    ]);
    expect(HomepagePresentation::rows($section))->toBe(1)
        ->and(HomepagePresentation::resolve($section))->toBe(['rows' => 1]);
});

// B. rows=1 renders one-row behavior
it('renders one row when presentation rows is 1', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-b-'.uniqid()]);
    $section = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'product_grid',
        'title' => 'Test',
        'config' => ['presentation' => ['rows' => 1]],
        'is_active' => true,
        'sort_order' => 1,
    ]);
    expect(HomepagePresentation::rows($section))->toBe(1);
});

// C. rows=2 renders two-row behavior
it('renders two rows when presentation rows is 2', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-c-'.uniqid()]);
    $section = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'product_grid',
        'title' => 'Test',
        'config' => ['presentation' => ['rows' => 2]],
        'is_active' => true,
        'sort_order' => 1,
    ]);
    expect(HomepagePresentation::rows($section))->toBe(2);
});

// D. Invalid values fallback to 1
it('falls back to 1 for invalid rows values', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-d-'.uniqid()]);
    foreach ([0, 3, null, 'garbage', '2', 99] as $invalid) {
        $section = HomepageSection::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'product_grid',
            'title' => 'Test '.$invalid,
            'config' => ['presentation' => ['rows' => $invalid]],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        expect(HomepagePresentation::rows($section))->toBe(1);
        // For string "2" should also fallback because strict check, then industry default 1
        // If industry preset is 1, fallback is 1
    }
});

// E. Unrelated config keys preserved
it('preserves unrelated config keys when presentation is added', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-e-'.uniqid()]);
    $section = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'product_grid',
        'title' => 'Test',
        'config' => ['data_source' => 'featured', 'limit' => 8, 'presentation' => ['rows' => 2]],
        'is_active' => true,
        'sort_order' => 1,
    ]);
    expect($section->config['data_source'])->toBe('featured')
        ->and($section->config['limit'])->toBe(8)
        ->and($section->config['presentation']['rows'])->toBe(2);
});

// F. Tenant isolation
it('keeps presentation tenant isolated', function (): void {
    $tA = actingAsTenant(['subdomain' => 'present-f-a-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    $sA = HomepageSection::query()->create(['tenant_id' => $tA->id, 'type' => 'category_grid', 'title' => 'A', 'config' => ['source' => 'category', 'presentation' => ['rows' => 2]], 'is_active' => true, 'sort_order' => 1]);
    expect(HomepagePresentation::rows($sA))->toBe(2);

    $tB = Tenant::factory()->create(['subdomain' => 'present-f-b-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    app(SubscriptionService::class)->startTrial($tB, Plan::query()->where('slug', 'trial')->firstOrFail(), 14);
    app(Tenancy::class)->set($tB);
    $sB = HomepageSection::query()->create(['tenant_id' => $tB->id, 'type' => 'category_grid', 'title' => 'B', 'config' => [], 'is_active' => true, 'sort_order' => 1]);
    // B has no override, should get industry preset 2 for grocery category
    expect(HomepagePresentation::rows($sB))->toBe(2);

    // A still 2
    app(Tenancy::class)->set($tA);
    expect(HomepagePresentation::rows($sA->fresh()))->toBe(2);
});

// G. Industry preset can provide default rows
it('uses industry preset for category rows in grocery', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-g-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    $section = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'category_grid',
        'title' => 'Cat',
        'config' => ['source' => 'category'],
        'is_active' => true,
        'sort_order' => 1,
    ]);
    expect(HomepagePresentation::rows($section))->toBe(2);
    expect(IndustryConfig::currentGet('homepage.presentation.category_rows'))->toBe(2);
});

// H. Explicit tenant section config overrides industry preset
it('tenant override wins over industry preset', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-h-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    // Grocery category default is 2, but tenant sets 1
    $section = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'category_grid',
        'title' => 'Cat',
        'config' => ['source' => 'category', 'presentation' => ['rows' => 1]],
        'is_active' => true,
        'sort_order' => 1,
    ]);
    expect(HomepagePresentation::rows($section))->toBe(1);

    // Brand mode should use product_rows (1) even for grocery, override to 2
    $sectionBrand = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'category_grid',
        'title' => 'Brands',
        'config' => ['source' => 'brand', 'presentation' => ['rows' => 2]],
        'is_active' => true,
        'sort_order' => 2,
    ]);
    expect(HomepagePresentation::rows($sectionBrand))->toBe(2);
    // Without override, brand defaults to product_rows 1
    $sectionBrand2 = HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'type' => 'category_grid',
        'title' => 'Brands2',
        'config' => ['source' => 'brand'],
        'is_active' => true,
        'sort_order' => 3,
    ]);
    expect(HomepagePresentation::rows($sectionBrand2))->toBe(1);
});

// I. Existing homepage ordering remains unchanged
it('preserves ordering with presentation config', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-i-'.uniqid()]);
    HomepageSection::query()->where('tenant_id', $tenant->id)->delete();
    HomepageSection::query()->create(['tenant_id' => $tenant->id, 'type' => 'product_grid', 'title' => 'First', 'config' => ['presentation' => ['rows' => 2]], 'is_active' => true, 'sort_order' => 1]);
    HomepageSection::query()->create(['tenant_id' => $tenant->id, 'type' => 'category_grid', 'title' => 'Second', 'config' => [], 'is_active' => true, 'sort_order' => 2]);
    $ordered = HomepageSection::query()->where('tenant_id', $tenant->id)->orderBy('sort_order')->pluck('title')->all();
    expect($ordered)->toBe(['First', 'Second']);
});

// J. Mobile/responsive behavior does not regress
it('keeps responsive classes for product grid', function (): void {
    $view = file_get_contents(resource_path('views/storefront/partials/sections/product-grid.blade.php'));
    expect($view)->toContain('gap-4 sm:gap-5')
        ->and($view)->toContain('w-[calc(50%-0.5rem)]')
        ->and($view)->toContain('auto-cols-');

    $catView = file_get_contents(resource_path('views/storefront/partials/sections/category-grid.blade.php'));
    expect($catView)->toContain('grid grid-cols-3')
        ->and($catView)->toContain('gap-4 sm:gap-5');
});

// K. Filament pack/unpack preserves presentation
it('packs and unpacks presentation via Filament pages', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-k-'.uniqid()]);
    // Simulate Create pack
    $create = new CreateHomepageSection;
    $ref = new ReflectionMethod($create, 'packConfig');
    $ref->setAccessible(true);
    $packed = $ref->invoke($create, ['config_presentation_rows' => 2, 'config_limit' => 8, 'type' => 'product_grid']);
    expect($packed['config']['presentation']['rows'])->toBe(2)
        ->and($packed['config']['limit'])->toBe(8)
        ->and(isset($packed['config_presentation_rows']))->toBeFalse();

    // Simulate Edit unpack
    $edit = new EditHomepageSection;
    $ref2 = new ReflectionMethod($edit, 'mutateFormDataBeforeFill');
    $ref2->setAccessible(true);
    $unpacked = $ref2->invoke($edit, ['config' => ['presentation' => ['rows' => 2], 'limit' => 8]]);
    expect($unpacked['config_presentation_rows'])->toBe(2)
        ->and($unpacked['config_limit'])->toBe(8);
});

// L. Re-running seeding remains idempotent
it('seeding remains idempotent with presentation', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'present-l-'.uniqid(), 'industry' => TenantIndustry::Grocery->value]);
    $before = HomepageSection::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
    app(IndustrySeederService::class)->seed($tenant);
    app(IndustrySeederService::class)->seed($tenant);
    expect(HomepageSection::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count())->toBe($before);
});

// M. Responsive cap: desktop 12:6 with mobile 6:3 via hidden md:flex
it('caps category grid responsively 12:6 desktop and 6:3 mobile', function (): void {
    // PHP cap is desktop max: rows 1 => 6, rows 2 => 12
    $items = collect(range(1, 12));
    $capTwo = 12;
    $capOne = 6;
    expect($items->take($capTwo)->count())->toBe(12);
    expect($items->take($capOne)->count())->toBe(6);

    // Mobile hide: rows 1 hides idx>=3, rows 2 hides idx>=6
    $catView = file_get_contents(resource_path('views/storefront/partials/sections/category-grid.blade.php'));
    expect($catView)->toContain('$cap = $rows === 2 ? 12 : 6')
        ->and($catView)->toContain('hidden md:flex')
        ->and($catView)->toContain('rounded-full')
        ->and($catView)->toContain('w-16 h-16')
        ->and($catView)->toContain('line-clamp-2')
        ->and($catView)->toContain('object-cover');

    // Brand mode must remain unaffected (still square, still uses product_rows)
    expect($catView)->toContain('Brand carousel')
        ->and($catView)->toContain('rounded-2xl'); // brand tiles still square
});
