<?php

declare(strict_types=1);

use App\Enums\StaticPageStatus;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\StaticPage;
use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/**
 * Renders a published product page through the tenant subdomain, returning the
 * server-rendered HTML.
 */
function policyPdpGet(string $slug, object $tenant): TestResponse
{
    return test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/product/'.$slug);
}

/**
 * Builds a published product plus its translation.
 */
function policyPdpProduct(array $translationData = []): array
{
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(array_merge([
        'locale' => 'en',
        'description' => '<h2>Key Features</h2><p>All-day battery.</p>',
    ], $translationData));

    return [$product, $translation, $tenant];
}

/**
 * Creates a StaticPage for a given tenant. tenant_id is purposefully guarded on
 * the model, so creation runs unguarded to force the intended tenant even when
 * a different tenant context is currently resolved.
 */
function policyPdpPage(object $tenant, string $slug, array $attributes = []): StaticPage
{
    $data = array_merge([
        'title' => ucwords(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'status' => StaticPageStatus::Published,
    ], $attributes);

    return StaticPage::unguarded(fn () => StaticPage::create($data + ['tenant_id' => $tenant->id]));
}

it('renders a policy link near the buy box for every published policy page', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct();

    policyPdpPage($tenant, 'delivery-policy');
    policyPdpPage($tenant, 'warranty-policy');
    policyPdpPage($tenant, 'emi-payment-policy');
    policyPdpPage($tenant, 'return-policy');
    policyPdpPage($tenant, 'authenticity-policy');

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('aria-label="Store policies"');
    expect($html)->toContain('/page/delivery-policy');
    expect($html)->toContain('/page/warranty-policy');
    expect($html)->toContain('/page/emi-payment-policy');
    expect($html)->toContain('/page/return-policy');
    expect($html)->toContain('/page/authenticity-policy');
});

it('skips policy pages that are not published', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct();

    policyPdpPage($tenant, 'warranty-policy');
    policyPdpPage($tenant, 'delivery-policy', ['status' => StaticPageStatus::Draft]);

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('/page/warranty-policy');
    expect($html)->not->toContain('/page/delivery-policy');
});

it('keeps the PDP intact when no matching policy pages exist', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct();

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('id="description"');
    expect($html)->not->toContain('aria-label="Store policies"');
    expect($html)->not->toContain('/page/');
});

it('restricts policy links to the storefront tenant', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct();

    $otherTenant = Tenant::factory()->create(['subdomain' => 'other-store']);
    policyPdpPage($otherTenant, 'delivery-policy');

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();
    expect($html)->not->toContain('/page/delivery-policy');

    policyPdpPage($tenant, 'delivery-policy');

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();
    expect($html)->toContain('/page/delivery-policy');
});

it('adds warranty to the section nav only when warranty info exists', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct([
        'warranty_info' => '12 month manufacturer warranty.',
    ]);

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('href="#warranty"');
    expect($html)->toContain('id="warranty"');
    expect($html)->toContain('12 month manufacturer warranty.');

    // Order follows the section layout: description -> warranty.
    expect(strpos($html, 'href="#description"'))->toBeLessThan(strpos($html, 'href="#warranty"'));
});

it('omits the warranty nav link when there is no warranty info', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct();

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->not->toContain('href="#warranty"');
    expect($html)->not->toContain('id="warranty"');
});

it('links the warranty section to the published warranty policy page', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct([
        'warranty_info' => '12 month manufacturer warranty.',
    ]);

    policyPdpPage($tenant, 'warranty-policy');

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    // Strip link + in-section "View Warranty Policy" link.
    expect(substr_count($html, '/page/warranty-policy'))->toBe(2);
    expect($html)->toContain('View Warranty Policy');
});

it('omits the in-section policy link when the policy page is unpublished', function (): void {
    [$product, $translation, $tenant] = policyPdpProduct([
        'warranty_info' => '12 month manufacturer warranty.',
    ]);

    policyPdpPage($tenant, 'warranty-policy', ['status' => StaticPageStatus::Draft]);

    $html = policyPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->not->toContain('View Warranty Policy');
    expect($html)->not->toContain('/page/warranty-policy');
});
