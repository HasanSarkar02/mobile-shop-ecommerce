<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Support\Facades\App;

function localeBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

it('serves EN at root with default locale', function (): void {
    $tenant = actingAsTenant(['locales' => ['en'], 'preferred_locale' => 'en']);
    $base = localeBase($tenant);

    $this->get($base.'/')->assertOk();
    expect(App::getLocale())->toBe('en');
});

it('returns 404 for /bn/ when tenant does not support bn', function (): void {
    $tenant = actingAsTenant(['locales' => ['en'], 'preferred_locale' => 'en']);
    $base = localeBase($tenant);

    $this->get($base.'/bn/')->assertNotFound();
});

it('serves BN at /bn/ prefix when tenant supports it and sets cookie', function (): void {
    $tenant = actingAsTenant(['locales' => ['en', 'bn'], 'preferred_locale' => 'en']);
    $base = localeBase($tenant);

    $response = $this->get($base.'/bn/');
    $response->assertOk();
    $response->assertHeader('Content-Language', 'bn');
    $response->assertCookie('storefront_locale', 'bn');
});

it('persists locale via cookie for subsequent EN-root requests', function (): void {
    $tenant = actingAsTenant(['locales' => ['en', 'bn'], 'preferred_locale' => 'en']);
    $base = localeBase($tenant);

    // First visit /bn/ to set cookie
    $this->get($base.'/bn/')->assertCookie('storefront_locale', 'bn');

    // Next request without prefix but with cookie should still be bn
    $response = $this->withCookie('storefront_locale', 'bn')->get($base.'/');
    $response->assertOk();
    $response->assertHeader('Content-Language', 'bn');
});

it('shows BN product name on /bn/ and falls back to EN when BN translation missing', function (): void {
    $tenant = actingAsTenant(['locales' => ['en', 'bn'], 'preferred_locale' => 'bn']);
    $base = localeBase($tenant);

    $productWithBn = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($productWithBn)->create(['locale' => 'en', 'name' => 'iPhone 13', 'slug' => 'iphone-13-en']);
    ProductTranslation::factory()->for($productWithBn)->create(['locale' => 'bn', 'name' => 'আইফোন ১৩', 'slug' => 'iphone-13-bn']);

    $productEnOnly = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($productEnOnly)->create(['locale' => 'en', 'name' => 'Galaxy S24', 'slug' => 'galaxy-s24']);

    // EN root shows EN names
    $htmlEn = $this->get($base.'/product/iphone-13-en')->assertOk()->getContent();
    expect($htmlEn)->toContain('iPhone 13');

    // BN route shows BN name
    $htmlBn = $this->get($base.'/bn/product/iphone-13-bn')->assertOk()->getContent();
    expect($htmlBn)->toContain('আইফোন ১৩');

    // Product without BN translation on BN route falls back to EN (no 404)
    $htmlFallback = $this->get($base.'/bn/product/galaxy-s24')->assertOk()->getContent();
    expect($htmlFallback)->toContain('Galaxy S24');
});

it('generates locale-prefixed canonical URLs via TenantUrlGenerator when on BN', function (): void {
    $tenant = actingAsTenant(['locales' => ['en', 'bn'], 'preferred_locale' => 'bn']);
    $base = localeBase($tenant);

    $product = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en', 'name' => 'Test Phone', 'slug' => 'test-phone']);
    ProductTranslation::factory()->for($product)->create(['locale' => 'bn', 'name' => 'টেস্ট ফোন', 'slug' => 'test-phone-bn']);

    // Visit BN prefix to set locale, then check that canonical URLs contain /bn
    $this->get($base.'/bn/product/test-phone-bn')->assertOk();

    $url = app(TenantUrlGenerator::class)->canonicalRoute($tenant, 'storefront.product', ['test-phone-bn']);
    expect($url)->toContain('/bn/product/test-phone-bn');
});

it('renders html lang attribute matching current locale', function (): void {
    $tenant = actingAsTenant(['locales' => ['en', 'bn'], 'preferred_locale' => 'en']);
    $base = localeBase($tenant);

    $htmlEn = $this->get($base.'/')->assertOk()->getContent();
    expect($htmlEn)->toContain('lang="en"');

    $htmlBn = $this->get($base.'/bn/')->assertOk()->getContent();
    expect($htmlBn)->toContain('lang="bn"');
});
