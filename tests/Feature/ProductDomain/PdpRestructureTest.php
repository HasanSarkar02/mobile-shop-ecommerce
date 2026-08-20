<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use App\Models\AttributeDefinition;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Renders a published product page the same way the storefront does (through
 * the tenant subdomain), returning the server-rendered HTML.
 */
function pdpStructureGet(string $slug, object $tenant): TestResponse
{
    return test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/product/'.$slug);
}

/**
 * Builds a published product plus its translation. Additional content
 * (attributes/reviews/FAQs) is attached per-test.
 */
function pdpStructureProduct(): array
{
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'description' => '<h2>Key Features</h2><p>All-day battery.</p>',
    ]);

    return [$product, $translation, $tenant];
}

it('renders the single-page structure with stable section IDs when content exists', function (): void {
    [$product, $translation, $tenant] = pdpStructureProduct();

    // Product-level specification.
    $screen = AttributeDefinition::query()->create(['code' => 'screen_size', 'label' => 'Screen Size', 'unit' => 'inch']);
    $product->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $screen->id,
        'value_string' => '6.7',
    ]);

    // Approved review (drives reviews_count + the reviews section content).
    $customer = Customer::factory()->create();
    $product->reviews()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'rating' => 5,
        'title' => 'Lovely device',
        'body' => 'Fast and bright.',
        'status' => ReviewStatus::Approved,
    ]);

    // Active FAQ.
    $product->faqs()->create([
        'tenant_id' => $tenant->id,
        'question' => 'Does it support wireless charging?',
        'answer' => 'Yes.',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $html = pdpStructureGet($translation->slug, $tenant)->assertOk()->getContent();

    // All four requirement sections are on the page with stable anchor IDs.
    expect($html)->toContain('id="specifications"');
    expect($html)->toContain('id="description"');
    expect($html)->toContain('id="reviews"');
    expect($html)->toContain('id="faq"');

    // The sticky section nav lists every populated section.
    expect($html)->toContain('href="#specifications"');
    expect($html)->toContain('href="#description"');
    expect($html)->toContain('href="#reviews"');
    expect($html)->toContain('href="#faq"');

    // scroll-margin used (mobile + desktop) so sections clear the sticky header.
    expect($html)->toContain('scroll-mt-32');
    expect($html)->toContain('lg:scroll-mt-[176px]');

    // Sticky nav offsets: mobile below the 64px header, desktop below the 112px header.
    expect($html)->toContain('sticky top-16');
    expect($html)->toContain('lg:top-28');

    // Content is server-rendered directly into the DOM.
    expect($html)->toContain('Screen Size');
    expect($html)->toContain('6.7');
    expect($html)->toContain('Key Features');
    expect($html)->toContain('Lovely device');
    expect($html)->toContain('Does it support wireless charging?');
});

it('omits nav links and sections that have no content', function (): void {
    [$product, $translation, $tenant] = pdpStructureProduct();

    // No description content, no reviews, no FAQs, no product-level specs.
    $translation->update(['description' => null]);

    $html = pdpStructureGet($translation->slug, $tenant)->assertOk()->getContent();

    // The nav is hidden entirely when every section is empty.
    expect($html)->not->toContain('aria-label="Product sections"');

    // Conditional sections are not rendered (nor linked) without content.
    expect($html)->not->toContain('id="description"');
    expect($html)->not->toContain('id="faq"');
});

it('only links nav sections that actually have content', function (): void {
    [$product, $translation, $tenant] = pdpStructureProduct();

    $translation->update(['description' => null]);

    $screen = AttributeDefinition::query()->create(['code' => 'screen_size', 'label' => 'Screen Size', 'unit' => 'inch']);
    $product->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $screen->id,
        'value_string' => '6.7',
    ]);

    $html = pdpStructureGet($translation->slug, $tenant)->assertOk()->getContent();

    // Specifications are present, so they are linked.
    expect($html)->toContain('href="#specifications"');

    // Description/Reviews/FAQ have no content, so they must not appear in the nav.
    expect($html)->not->toContain('href="#description"');
    expect($html)->not->toContain('href="#reviews"');
    expect($html)->not->toContain('href="#faq"');
});

it('keeps section content in the DOM rather than behind tab switching', function (): void {
    [$product, $translation, $tenant] = pdpStructureProduct();

    $screen = AttributeDefinition::query()->create(['code' => 'screen_size', 'label' => 'Screen Size', 'unit' => 'inch']);
    $product->attributeValues()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $screen->id,
        'value_string' => '6.7',
    ]);

    $customer = Customer::factory()->create();
    $product->reviews()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'rating' => 4,
        'body' => 'Great value.',
        'status' => ReviewStatus::Approved,
    ]);

    $html = pdpStructureGet($translation->slug, $tenant)->assertOk()->getContent();

    // The old tab-based implementation drove visibility off `x-show="tab === ..."`.
    // Those must be gone — every section is a plain, always-visible server-rendered
    // <section> so content survives without JavaScript.
    expect($html)->not->toContain('x-show="tab');
    expect($html)->not->toContain("tab === 'spec'");

    // Specifications, Description and Reviews exist as real server-rendered
    // <section> elements in this fixture (no FAQ built here).
    expect(substr_count($html, '<section'))->toBeGreaterThanOrEqual(3);
    expect($html)->toContain('id="specifications"');
    expect($html)->toContain('id="description"');
    expect($html)->toContain('id="reviews"');
});

it('exposes the section content without JavaScript', function (): void {
    [$product, $translation, $tenant] = pdpStructureProduct();

    $product->faqs()->create([
        'tenant_id' => $tenant->id,
        'question' => 'Warranty length?',
        'answer' => '12 months.',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $html = pdpStructureGet($translation->slug, $tenant)->assertOk()->getContent();

    // FAQ question + answer are present in the raw HTML payload.
    expect($html)->toContain('Warranty length?');
    expect($html)->toContain('12 months.');

    // Description markup is baked into the page, not fetched client-side.
    expect($html)->toContain('<h2>Key Features</h2>');
});

it('renders sanitized inline description images with src and alt on the storefront', function (): void {
    [$product, $translation, $tenant] = pdpStructureProduct();

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    $media = $translation
        ->addMediaFromString($png)
        ->usingFileName('pixel.png')
        ->toMediaCollection('description_images');

    $translation->update([
        'description' => '<h2>Key Features</h2><p>All-day battery.</p>'
            .'<p><img src="'.$media->getUrl().'" alt="Inline shot" data-id="'.$media->uuid.'" loading="lazy"></p>',
    ]);

    $html = pdpStructureGet($translation->slug, $tenant)->assertOk()->getContent();

    $descriptionSection = (string) Str::of($html)
        ->between('<section id="description"', '</section>')
        ->toString();

    expect($html)->toContain('id="description"');
    expect($descriptionSection)->toContain('<h2>Key Features</h2>');
    expect($descriptionSection)->toContain($media->getUrl());
    expect($descriptionSection)->toContain('alt="Inline shot"');
    expect($descriptionSection)->not->toContain('data-id');
    expect($descriptionSection)->not->toContain('loading=');
});
