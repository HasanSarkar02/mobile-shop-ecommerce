<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\IndustryConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('debug catalog grid with 2 products', function (): void {
    $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
    $category = Category::query()->where('tenant_id', $tenant->id)->where('slug', 'smartphones')->firstOrFail();
    // Remove existing products in this category to ensure exactly 2
    Product::query()->where('category_id', $category->id)->delete();
    for ($i = 0; $i < 2; $i++) {
        $product = Product::factory()->create(['status' => 'published', 'category_id' => $category->id]);
        ProductTranslation::factory()->for($product)->create(['locale' => 'en', 'name' => 'Phone '.$i, 'slug' => 'phone-'.$i.'-'.uniqid()]);
        $variant = ProductVariant::factory()->for($product)->create(['price' => 1000000 + $i * 100]);
        app(InventoryService::class)->restock($variant, 10);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $product->addMediaFromString($png)->usingFileName("test-{$i}.png")->toMediaCollection('images');
    }

    expect(IndustryConfig::currentGet('ui.grid_class'))->toBe('grid-cols-2 md:grid-cols-3 lg:grid-cols-4');
    expect(IndustryConfig::currentGet('ui.container_class'))->toBe('max-w-[1440px] mx-auto');
    expect(IndustryConfig::currentGet('ui.card_component'))->toBe('storefront.product-cards.electronics');

    $base = 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
    $response = $this->get($base.'/category/'.$category->slug);
    $response->assertOk();
    $html = $response->getContent();

    // Dump relevant snippet to file for inspection
    $snippet = '';
    if (preg_match('/<div class="grid[^"]*".*?<\/div>\s*<div class="mt-10">/s', $html, $m)) {
        $snippet = $m[0];
    } else {
        // fallback: extract grid div
        if (preg_match('/<div class="grid[^>]*>/', $html, $m2)) {
            $snippet = substr($html, strpos($html, $m2[0]), 2000);
        }
    }
    // Write to temp file
    file_put_contents(storage_path('app/catalog_debug.html'), $html);
    file_put_contents(storage_path('app/catalog_snippet.html'), $snippet);

    // Check for suspicious centering classes in ancestors
    expect($html)->toContain('flex flex-col lg:flex-row gap-8');
    expect($html)->toContain('flex-1 min-w-0');
    // Grid should have expected classes
    expect($html)->toContain('grid-cols-2 md:grid-cols-3 lg:grid-cols-4');

    // Ensure grid has correct gap and explicit left-alignment (fix for huge-gap bug)
    $needle = 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4';
    $pos = strpos($html, $needle);
    expect($pos)->not->toBeFalse();
    $start = strrpos(substr($html, 0, $pos), '<div');
    $gridTag = substr($html, $start, 600);
    expect($gridTag)->toContain('gap-x-4');
    expect($gridTag)->toContain('gap-y-8');
    expect($gridTag)->toContain('sm:gap-x-6');
    expect($gridTag)->toContain('w-full');
    expect($gridTag)->toContain('justify-start');
    expect($gridTag)->toContain('justify-items-stretch');
    // Grid should not have centering utilities
    expect($gridTag)->not->toContain('justify-center');
    expect($gridTag)->not->toContain('justify-between');
    expect($gridTag)->not->toContain('place-items-center');
    // Product area should be flex-1 w-full
    expect($html)->toContain('flex-1 min-w-0 w-full');
    // Output for manual inspection
    echo "\n---GRID TAG---\n".$gridTag."\n---END---\n";
    echo "\n---SNIPPET---\n".substr($snippet, 0, 2000)."\n---END SNIPPET---\n";
});

foreach ([1, 3, 4] as $count) {
    it("catalog grid with {$count} products remains left-aligned", function () use ($count): void {
        $tenant = actingAsTenant(['status' => 'active', 'industry' => 'electronics']);
        $category = Category::query()->where('tenant_id', $tenant->id)->where('slug', 'smartphones')->firstOrFail();
        Product::query()->where('category_id', $category->id)->delete();
        for ($i = 0; $i < $count; $i++) {
            $product = Product::factory()->create(['status' => 'published', 'category_id' => $category->id]);
            ProductTranslation::factory()->for($product)->create(['locale' => 'en', 'name' => 'Phone '.$i, 'slug' => 'phone-'.$i.'-'.uniqid()]);
            $variant = ProductVariant::factory()->for($product)->create(['price' => 1000000 + $i * 100]);
            app(InventoryService::class)->restock($variant, 10);
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            $product->addMediaFromString($png)->usingFileName("test-{$count}-{$i}.png")->toMediaCollection('images');
        }
        $base = 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
        $html = $this->get($base.'/category/'.$category->slug)->assertOk()->getContent();
        $needle = 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4';
        $pos = strpos($html, $needle);
        expect($pos)->not->toBeFalse();
        $start = strrpos(substr($html, 0, $pos), '<div');
        $gridTag = substr($html, $start, 600);
        expect($gridTag)->toContain('w-full');
        expect($gridTag)->toContain('justify-start');
        expect($gridTag)->toContain('justify-items-stretch');
        // Count rendered cards
        $cardCount = substr_count($html, 'group relative flex flex-col overflow-hidden rounded-2xl');
        // Should be at least $count (Livewire may render skeletons too, but we check >=)
        expect($cardCount)->toBeGreaterThanOrEqual($count);
    });
}
