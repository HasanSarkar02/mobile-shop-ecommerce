<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
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

it('sanitizes realistic rich-editor output and preserves inline media src and alt', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'description' => '<h2>Overview</h2><p>All-day battery.</p>'
            .'<table><tbody><tr><th colspan="2">Specs</th></tr><tr><td>Weight</td><td>210g</td></tr></tbody></table>'
            .'<p><img src="https://cdn.example.com/uploads/abc.webp" alt="Midnight Black" data-id="abc-123" loading="lazy"></p>'
            .'<ul><li>5G ready</li></ul>',
    ]);

    $html = $translation->sanitizedDescription();

    expect($html)->toContain('<h2>Overview</h2>');
    expect($html)->toContain('<table>');
    expect($html)->toContain('<th colspan="2">Specs</th>');
    expect($html)->toContain('https://cdn.example.com/uploads/abc.webp');
    expect($html)->toContain('alt="Midnight Black"');
    expect($html)->toContain('<ul><li>5G ready</li></ul>');
    expect($html)->not->toContain('data-id');
    expect($html)->not->toContain('loading=');
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
    $media = new Media;
    $media->custom_properties = ['alt' => 'Midnight Black phone'];

    expect(media_alt($media, 'fallback'))->toBe('Midnight Black phone');
    expect(media_alt(new Media, 'fallback'))->toBe('fallback');
});

it('wires the description rich-content attribute to the spatie media library provider with public visibility', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    $attribute = $translation->getRichContentAttribute('description');

    expect($attribute)->not->toBeNull();
    expect($attribute->getName())->toBe('description');
    expect($attribute->getFileAttachmentsVisibility())->toBe('public');

    $provider = $attribute->getFileAttachmentProvider();

    expect($provider)->toBeInstanceOf(SpatieMediaLibraryFileAttachmentProvider::class);
    expect($provider->getCollection())->toBe('description_images');
    expect($provider->getDefaultFileAttachmentVisibility())->toBe('private');
});

it('stores description inline images in the description_images media collection and renders their src through the sanitizer', function (): void {
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    $media = $translation
        ->addMediaFromString($png)
        ->usingFileName('pixel.png')
        ->toMediaCollection('description_images');

    $uuid = $media->uuid;
    $url = $media->getUrl();

    expect($translation->getMedia('description_images'))->toHaveCount(1);
    expect($media->collection_name)->toBe('description_images');

    $translation->update([
        'description' => '<p><img src="'.$url.'" alt="Inline shot" data-id="'.$uuid.'" loading="lazy"></p>',
    ]);

    $html = $translation->sanitizedDescription();

    expect($html)->toContain($url);
    expect($html)->toContain('alt="Inline shot"');
    expect($html)->not->toContain('data-id');
    expect($html)->not->toContain('loading=');
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
