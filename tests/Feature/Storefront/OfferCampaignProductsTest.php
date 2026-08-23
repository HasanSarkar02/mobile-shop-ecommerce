<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Enums\HomepageSectionType;
use App\Enums\ProductStatus;
use App\Enums\Visibility;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\Storefront\CampaignProductResolver;
use App\Services\Storefront\HomepageSectionRenderer;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function cmpTenant(string $subdomain): Tenant
{
    return actingAsTenant(['subdomain' => $subdomain]);
}

function cmpCampaign(object $tenant, array $overrides = []): Campaign
{
    app(Tenancy::class)->set($tenant);

    return Campaign::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Mega Sale',
        'status' => CampaignStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(7),
    ], $overrides));
}

function cmpProduct(object $tenant, string $name, array $variantOverrides = []): Product
{
    app(Tenancy::class)->set($tenant);

    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en', 'name' => $name]);

    ProductVariant::factory()->for($product)->create(array_merge([
        'price' => 1000000,
        'compare_at_price' => null,
    ], $variantOverrides));

    return $product;
}

function cmpAttach(Campaign $campaign, array $productsWithSort): void
{
    $pivot = [];

    foreach ($productsWithSort as $sortOrder => $product) {
        $pivot[$product->id] = ['sort_order' => $sortOrder];
    }

    $campaign->products()->attach($pivot);
}

it('shows eligible attached products on the offer detail page', function (): void {
    $tenant = cmpTenant('ocp-show');
    $campaign = cmpCampaign($tenant);
    cmpAttach($campaign, [1 => cmpProduct($tenant, 'Deal Phone A')]);

    $html = get('http://ocp-show.'.config('tenancy.central_domain').'/offer/mega-sale')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Deal Phone A');
});

it('excludes draft products from the offer detail page', function (): void {
    $tenant = cmpTenant('ocp-draft-product');
    $campaign = cmpCampaign($tenant);

    $published = cmpProduct($tenant, 'Visible Deal');
    $draft = Product::factory()
        ->has(ProductVariant::factory()->count(1)->state(['price' => 1000000]), 'variants')
        ->create(['status' => ProductStatus::Draft]);
    ProductTranslation::factory()->for($draft)->create(['locale' => 'en', 'name' => 'Hidden Draft']);
    cmpAttach($campaign, [1 => $published, 2 => $draft]);

    $html = get('http://ocp-draft-product.'.config('tenancy.central_domain').'/offer/mega-sale')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Visible Deal')
        ->and($html)->not->toContain('Hidden Draft');
});

it('respects campaign_product.sort_order on the offer detail page', function (): void {
    $tenant = cmpTenant('ocp-sort');
    $campaign = cmpCampaign($tenant);
    cmpAttach($campaign, [
        30 => cmpProduct($tenant, 'Alpha Item'),
        10 => cmpProduct($tenant, 'Beta Item'),
        20 => cmpProduct($tenant, 'Gamma Item'),
    ]);

    $html = get('http://ocp-sort.'.config('tenancy.central_domain').'/offer/mega-sale')->assertOk()->getContent();

    expect(mb_strpos($html, 'Beta Item'))
        ->toBeLessThan(mb_strpos($html, 'Gamma Item'))
        ->and(mb_strpos($html, 'Gamma Item'))->toBeLessThan(mb_strpos($html, 'Alpha Item'));
});

it('sorts offers newest first by default', function (): void {
    $tenant = cmpTenant('ocp-newest');
    cmpCampaign($tenant, ['name' => 'Older Offer', 'starts_at' => now()->subDays(2)]);
    cmpCampaign($tenant, ['name' => 'Fresher Offer', 'starts_at' => now()->subHours(2)]);

    $html = get('http://ocp-newest.'.config('tenancy.central_domain').'/offers')->assertOk()->getContent();

    expect(mb_strpos($html, 'Fresher Offer'))->toBeLessThan(mb_strpos($html, 'Older Offer'));
});

it('sorts offers ending soon on demand', function (): void {
    $tenant = cmpTenant('ocp-ending');
    cmpCampaign($tenant, ['name' => 'Far End Offer', 'ends_at' => now()->addDays(7)]);
    cmpCampaign($tenant, ['name' => 'Near End Offer', 'ends_at' => now()->addDay()]);
    cmpCampaign($tenant, ['name' => 'Open End Offer', 'ends_at' => null]);

    $html = get('http://ocp-ending.'.config('tenancy.central_domain').'/offers?sort=ending_soon')
        ->assertOk()
        ->getContent();

    expect(mb_strpos($html, 'Near End Offer'))
        ->toBeLessThan(mb_strpos($html, 'Far End Offer'))
        ->and(mb_strpos($html, 'Far End Offer'))->toBeLessThan(mb_strpos($html, 'Open End Offer'));
});

it('shows an up-to discount calculated from attached product compare-at prices', function (): void {
    $tenant = cmpTenant('ocp-discount');
    $campaign = cmpCampaign($tenant);
    cmpAttach($campaign, [
        1 => cmpProduct($tenant, 'Cheap Deal', ['price' => 800000, 'compare_at_price' => 1000000]),
        2 => cmpProduct($tenant, 'Better Deal', ['price' => 700000, 'compare_at_price' => 1000000]),
    ]);

    $show = get('http://ocp-discount.'.config('tenancy.central_domain').'/offer/mega-sale')->assertOk()->getContent();
    $index = get('http://ocp-discount.'.config('tenancy.central_domain').'/offers')->assertOk()->getContent();

    expect($show)->toContain('30% off')
        ->and($index)->toContain('Up to')
        ->and($index)->toContain('30%');
});

it('never shows a discount figure when no product has a compare-at price', function (): void {
    $tenant = cmpTenant('ocp-nodiscount');
    $campaign = cmpCampaign($tenant);
    cmpAttach($campaign, [1 => cmpProduct($tenant, 'Full Price Item', ['price' => 500000])]);

    $resolver = app(CampaignProductResolver::class);

    expect($resolver->maxDiscountForCampaign($campaign))->toBeNull();

    $index = get('http://ocp-nodiscount.'.config('tenancy.central_domain').'/offers')->assertOk()->getContent();

    expect($index)->not->toContain('Up to');
});

it('shows currently valid campaign coupons and hides expired or inactive ones', function (): void {
    $tenant = cmpTenant('ocp-coupons');
    $campaign = cmpCampaign($tenant);

    Coupon::query()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'code' => 'LIVE10',
        'name' => 'Live Coupon',
        'type' => 'percentage',
        'value' => 10,
    ]);
    Coupon::query()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'code' => 'OLDCODE',
        'name' => 'Expired Coupon',
        'type' => 'percentage',
        'value' => 10,
        'ends_at' => now()->subDay(),
    ]);
    Coupon::query()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'code' => 'SLEEPY5',
        'name' => 'Inactive Coupon',
        'type' => 'percentage',
        'value' => 5,
        'is_active' => false,
    ]);

    $html = get('http://ocp-coupons.'.config('tenancy.central_domain').'/offer/mega-sale')->assertOk()->getContent();

    expect($html)->toContain('LIVE10')
        ->and($html)->not->toContain('OLDCODE')
        ->and($html)->not->toContain('SLEEPY5');
});

it('only exposes eligible campaigns through the homepage campaign product source', function (): void {
    $tenant = cmpTenant('ocp-home');
    $live = cmpCampaign($tenant);
    $ended = cmpCampaign($tenant, ['slug' => 'ended-sale', 'ends_at' => now()->subHour()]);

    $first = cmpProduct($tenant, 'Home Deal One');
    $second = cmpProduct($tenant, 'Home Deal Two');
    cmpAttach($live, [1 => $second, 2 => $first]);
    cmpAttach($ended, [1 => cmpProduct($tenant, 'Zombie Deal')]);

    DB::table('homepage_sections')->insert([
        'tenant_id' => $tenant->id,
        'campaign_id' => $live->id,
        'type' => HomepageSectionType::ProductGrid->value,
        'title' => 'Deals',
        'config' => json_encode(['data_source' => 'campaign', 'limit' => 8]),
        'visibility' => Visibility::All->value,
        'is_active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $sectionId = (int) DB::table('homepage_sections')->where('tenant_id', $tenant->id)->max('id');

    $section = HomepageSection::query()->findOrFail($sectionId);
    $resolved = app(HomepageSectionRenderer::class)->resolveProducts($section);

    expect($resolved->pluck('id')->values()->all())->toBe([$second->id, $first->id]);

    DB::table('homepage_sections')->where('id', $sectionId)->update(['campaign_id' => $ended->id]);
    $endedSection = HomepageSection::query()->findOrFail($sectionId);

    expect(app(HomepageSectionRenderer::class)->resolveProducts($endedSection))->toBeEmpty();
});

it('renders an uploaded hero image on the offer detail and index pages', function (): void {
    $tenant = cmpTenant('ocp-heroimg');
    cmpCampaign($tenant, ['slug' => 'mega-sale', 'hero_image' => 'campaign-heroes/summer-2026.jpg']);

    $show = get('http://ocp-heroimg.'.config('tenancy.central_domain').'/offer/mega-sale')
        ->assertOk()
        ->getContent();
    $index = get('http://ocp-heroimg.'.config('tenancy.central_domain').'/offers')
        ->assertOk()
        ->getContent();

    expect($show)->toContain('campaign-heroes/summer-2026.jpg')
        ->and($index)->toContain('campaign-heroes/summer-2026.jpg');
});

it('uses card images on offer cards and falls back to the hero image', function (): void {
    $tenant = cmpTenant('ocp-cardimg');
    cmpCampaign($tenant, ['slug' => 'card-one', 'card_image' => 'campaign-cards/card.jpg']);
    cmpCampaign($tenant, ['slug' => 'card-two', 'hero_image' => 'campaign-heroes/fallback.jpg']);

    $html = get('http://ocp-cardimg.'.config('tenancy.central_domain').'/offers')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('campaign-cards/card.jpg')
        ->and($html)->toContain('campaign-heroes/fallback.jpg');
});

it('falls back to campaign banner artwork when no image is uploaded', function (): void {
    $tenant = cmpTenant('ocp-bannerfb');
    $campaign = cmpCampaign($tenant);
    $banner = Banner::query()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'title' => 'Summer artwork',
        'is_active' => true,
    ]);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $banner->addMediaFromString($png)
        ->usingFileName('summer-art.png')
        ->toMediaCollection('image');

    $show = get('http://ocp-bannerfb.'.config('tenancy.central_domain').'/offer/mega-sale')
        ->assertOk()
        ->getContent();

    expect($show)->toContain('summer-art');
});
