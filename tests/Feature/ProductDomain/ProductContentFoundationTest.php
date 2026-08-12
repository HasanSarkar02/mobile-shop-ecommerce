<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('sanitizes rich product descriptions with the storefront product profile', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'description' => '<h2>Key Features</h2><ul><li><strong>Fast</strong> & <em>light</em></li></ul>'
            .'<p><a href="https://example.com" rel="nofollow">Link</a></p>'
            .'<table><thead><tr><th>Spec</th></tr></thead><tbody><tr><td>Value</td></tr></tbody></table>'
            .'<img src="https://example.com/phone.jpg" alt="Phone">'
            .'<script>alert(1)</script><p onclick="steal()">Safe</p>',
    ]);

    $html = $translation->sanitizedDescription();

    expect($html)->toContain('<h2>Key Features</h2>');
    expect($html)->toContain('<ul>');
    expect($html)->toContain('<li><strong>Fast</strong> &amp; <em>light</em></li>');
    expect($html)->toContain('<a href="https://example.com" rel="nofollow">Link</a>');
    expect($html)->toContain('<thead>');
    expect($html)->toContain('<th>Spec</th>');
    expect($html)->toContain('<td>Value</td>');
    expect($html)->toContain('<img src="https://example.com/phone.jpg" alt="Phone"');
    expect($html)->not->toContain('<script');
    expect($html)->not->toContain('onclick');
});

it('wraps plain-text descriptions in paragraphs', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'description' => "First line.\n\nSecond paragraph.",
    ]);

    $html = $translation->sanitizedDescription();

    expect($html)->toContain('<p>First line.</p>');
    expect($html)->toContain('<p>Second paragraph.</p>');
});

it('returns null for empty descriptions', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'description' => null,
    ]);

    expect($translation->sanitizedDescription())->toBeNull();
});

it('caches the sanitized description and invalidates it when content changes', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'description' => '<h2>Version 1</h2>',
    ]);

    $keyV1 = 'product-description:'.$product->id.':en:'.md5('<h2>Version 1</h2>');

    expect($translation->sanitizedDescription())->toContain('<h2>Version 1</h2>');
    expect(Cache::has($keyV1))->toBeTrue();

    $translation->update(['description' => '<h2>Version 2</h2>']);

    expect($translation->sanitizedDescription())->toContain('<h2>Version 2</h2>');

    $keyV2 = 'product-description:'.$product->id.':en:'.md5('<h2>Version 2</h2>');
    expect(Cache::has($keyV2))->toBeTrue();
});

it('resolves image alt text from media custom properties with a fallback', function (): void {
    $media = new Media();
    $media->custom_properties = ['alt' => 'Midnight Black phone'];

    expect(media_alt($media, 'fallback'))->toBe('Midnight Black phone');
    expect(media_alt(new Media(), 'fallback'))->toBe('fallback');
});

it('registers the small image conversion on products and variants', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'TEST-1']);

    $product->registerAllMediaConversions();
    $variant->registerAllMediaConversions();

    $productNames = collect($product->mediaConversions)->map(fn ($conversion) => $conversion->getName());
    $variantNames = collect($variant->mediaConversions)->map(fn ($conversion) => $conversion->getName());

    expect($productNames)->toContain('small');
    expect($variantNames)->toContain('small');
});