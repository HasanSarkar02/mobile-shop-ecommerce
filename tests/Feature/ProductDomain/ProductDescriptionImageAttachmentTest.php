<?php

declare(strict_types=1);

use App\Filament\Store\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Store\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;

function testPngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
}

function descriptionEditorStatePath(int|string $itemKey): string
{
    return "data.translations.record-{$itemKey}.description";
}

it('stores a rich editor image in description_images when creating a product', function (): void {
    $tenant = actingAsTenant();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    $this->actingAs($user);

    $attachmentId = (string) Str::orderedUuid();

    $response = Livewire::test(CreateProduct::class);

    $response->upload(
        "componentFileAttachments.data.translations.0.description.{$attachmentId}",
        [UploadedFile::fake()->image('hero.png')],
    );

    $response->fillForm([
        'translations' => [
            [
                'locale' => 'en',
                'name' => 'Galaxy X',
                'slug' => 'galaxy-x',
                'description' => '<p>Intro</p>'
                    .'<p><img src="http://localhost/livewire-preview/'.$attachmentId.'" data-id="'.$attachmentId.'" alt="Hero"></p>',
            ],
        ],
        'status' => 'draft',
    ]);

    $response->call('create');

    $product = Product::query()->first();
    $translation = $product->translations()->first();

    $media = $translation->getMedia('description_images');

    expect($media)->toHaveCount(1);

    $uuid = $media->first()->uuid;
    $url = $media->first()->getUrl();

    expect($translation->description)->toContain($uuid);
    expect($translation->description)->toContain('data-id="'.$uuid.'"');
    expect($translation->description)->toContain('alt="Hero"');
    expect($translation->description)->toContain($url);
    expect($translation->description)->not->toContain('livewire-preview');

    $html = $translation->sanitizedDescription();

    expect($html)->toContain($url);
    expect($html)->toContain('alt="Hero"');
    expect($html)->not->toContain('data-id');
});

it('stores a rich editor image attached while editing a product', function (): void {
    $tenant = actingAsTenant();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    $this->actingAs($user);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'tenant_id' => $tenant->id,
        'description' => '<p>Original</p>',
    ]);

    $attachmentId = (string) Str::orderedUuid();

    $response = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);

    $response->upload(
        'componentFileAttachments.'.descriptionEditorStatePath($translation->getKey()).".{$attachmentId}",
        [UploadedFile::fake()->image('hero.png')],
    );

    $response->fillForm([
        'translations' => [
            'record-'.$translation->getKey() => [
                'locale' => 'en',
                'name' => $translation->name,
                'slug' => $translation->slug,
                'description' => '<p>Original</p>'
                    .'<p><img src="http://localhost/livewire-preview/'.$attachmentId.'" data-id="'.$attachmentId.'" alt="New"></p>',
            ],
        ],
        'status' => 'draft',
    ]);

    $response->call('save');

    $fresh = $translation->fresh();
    $media = $fresh->getMedia('description_images');

    expect($media)->toHaveCount(1);

    $uuid = $media->first()->uuid;
    $url = $media->first()->getUrl();

    expect($fresh->description)->toContain('Original');
    expect($fresh->description)->toContain($uuid);
    expect($fresh->description)->toContain('data-id="'.$uuid.'"');
    expect($fresh->description)->toContain('alt="New"');
    expect($fresh->description)->toContain($url);
    expect($fresh->description)->not->toContain('livewire-preview');

    $html = $fresh->sanitizedDescription();

    expect($html)->toContain($url);
    expect($html)->toContain('alt="New"');
    expect($html)->not->toContain('data-id');
});

it('keeps existing description_images media when editing a product', function (): void {
    $tenant = actingAsTenant();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    $this->actingAs($user);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'tenant_id' => $tenant->id,
        'description' => '<p>Original</p>',
    ]);

    $media = $translation
        ->addMediaFromString(testPngBytes())
        ->usingFileName('existing.png')
        ->toMediaCollection('description_images');

    $uuid = $media->uuid;
    $url = $media->getUrl();

    $translation->update([
        'description' => '<p>Original</p><p><img src="'.$url.'" alt="Existing" data-id="'.$uuid.'"></p>',
    ]);

    $response = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);

    $response->fillForm([
        'translations' => [
            'record-'.$translation->getKey() => [
                'locale' => 'en',
                'name' => $translation->name,
                'slug' => $translation->slug,
                'description' => '<p>Original</p><p><img src="'.$url.'" alt="Existing" data-id="'.$uuid.'"></p><p>Updated copy.</p>',
            ],
        ],
        'status' => 'draft',
    ]);

    $response->call('save');

    $fresh = $translation->fresh();

    expect($fresh->getMedia('description_images'))->toHaveCount(1);
    expect($fresh->description)->toContain($uuid);
    expect($fresh->description)->toContain($url);
    expect($fresh->description)->toContain('alt="Existing"');
    expect($fresh->description)->toContain('Updated copy.');
});

it('rejects a rich editor image referencing another tenants media', function (): void {
    $tenantA = actingAsTenant();

    $productA = Product::factory()->create(['tenant_id' => $tenantA->id]);
    $translationA = ProductTranslation::factory()->for($productA)->create(['locale' => 'en', 'tenant_id' => $tenantA->id]);

    $mediaA = $translationA
        ->addMediaFromString(testPngBytes())
        ->usingFileName('other.png')
        ->toMediaCollection('description_images');

    $user = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => 'owner',
    ]);

    $this->actingAs($user);

    $tenantB = Tenant::factory()->create();
    app(Tenancy::class)->set($tenantB);

    $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);
    $translationB = ProductTranslation::factory()->for($productB)->create([
        'locale' => 'en',
        'tenant_id' => $tenantB->id,
        'description' => '<p>Original</p>',
    ]);

    $response = Livewire::test(EditProduct::class, ['record' => $productB->getRouteKey()]);

    $response->fillForm([
        'translations' => [
            'record-'.$translationB->getKey() => [
                'locale' => 'en',
                'name' => $translationB->name,
                'slug' => $translationB->slug,
                'description' => '<p>Original</p><p><img src="http://localhost/x.png" data-id="'.$mediaA->uuid.'" alt="Stolen"></p>',
            ],
        ],
        'status' => 'draft',
    ]);

    expect(fn () => $response->call('save'))
        ->not->toThrow(Throwable::class);

    expect($response->errors()->all())->toContain('The description field contains a file path that is not permitted.');

    expect($translationB->fresh()->getMedia('description_images'))->toHaveCount(0);
    expect($translationB->fresh()->description)->toBe('<p>Original</p>');
});
