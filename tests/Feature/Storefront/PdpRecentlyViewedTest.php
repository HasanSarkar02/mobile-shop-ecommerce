<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

function recentlyViewedBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function recentlyViewedProduct(string $modelNumber): Product
{
    $product = Product::factory()->create(['status' => 'published', 'model_number' => $modelNumber]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create();
    app(InventoryService::class)->restock($variant, 10);

    return $product;
}

it('does not query for recent products when the recently-viewed list is empty', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $current = recentlyViewedProduct('CUR-001');
    $slug = $current->translation('en')->slug;
    $base = recentlyViewedBase($tenant);

    // No recently-viewed cookie set, so the rail must not even issue a query.
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->not->toContain('Recently Viewed');
    // The rail query is the only product query shaped `from products ... id in (...)`.
    expect(collect($queries)->filter(
        fn (string $sql) => str_contains($sql, 'from `products`') && str_contains($sql, ' in ('),
    ))->toHaveCount(0);
});

it('renders a recently-viewed rail excluding the current product, most-recent-first', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);

    $oldest = recentlyViewedProduct('REC-001');
    $middle = recentlyViewedProduct('REC-002');
    $newest = recentlyViewedProduct('REC-003');
    $current = recentlyViewedProduct('CUR-001');

    // Simulate a guest who browsed oldest → middle → newest. The service stores
    // ids most-recent-first in the `recently_viewed` cookie.
    $cookie = implode(',', [$newest->id, $middle->id, $oldest->id]);

    $slug = $current->translation('en')->slug;
    $base = recentlyViewedBase($tenant);

    $html = $this->withCookie('recently_viewed', $cookie)
        ->get($base.'/product/'.$slug)
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Recently Viewed');

    // The current product must be excluded from its own rail. Scoped to the rail
    // section because the product's own canonical URL also contains the slug.
    $railStart = strpos($html, 'Recently Viewed');
    expect($railStart)->not->toBe(false);
    $railSection = substr($html, $railStart, 20000);
    expect($railSection)->not->toContain('/product/'.$current->translation('en')->slug);

    $positions = [
        $newest->translation('en')->slug,
        $middle->translation('en')->slug,
        $oldest->translation('en')->slug,
    ];

    // All three previously-viewed products appear, in most-recent-first order.
    $offsets = array_map(fn (string $s) => strpos($html, '/product/'.$s), $positions);
    expect($offsets[0])->not->toBe(false);
    expect($offsets[1])->not->toBe(false);
    expect($offsets[2])->not->toBe(false);
    expect($offsets[0] < $offsets[1] && $offsets[1] < $offsets[2])->toBeTrue();
});
