<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\Tenant;
use App\Services\Storefront\ProductListingService;
use App\Support\ProductFilterState;

function collectionBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function createCollectionProduct(string $name): Product
{
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'is_featured' => false,
    ]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en', 'name' => $name]);

    return $product;
}

function attachWithSortOrder(Collection $collection, array $sortOrders): array
{
    $products = [];
    $pivot = [];

    foreach ($sortOrders as $sortOrder) {
        $product = createCollectionProduct('Product '.$sortOrder);
        $products[] = $product;
        $pivot[$product->id] = ['sort_order' => $sortOrder];
    }

    $collection->products()->attach($pivot);

    return $products;
}

beforeEach(function () {
    $this->tenant = actingAsTenant(['subdomain' => 'demo', 'status' => 'active']);
});

it('loads the collection listing page without a MySQL distinct error', function () {
    $collection = Collection::query()->create(['name' => 'Flash Sale', 'slug' => 'flash-sale', 'is_active' => true]);
    attachWithSortOrder($collection, [1, 2, 3]);

    $this->get(collectionBaseUrl($this->tenant).'/collection/flash-sale')
        ->assertOk()
        ->assertSee('Flash Sale');
});

it('preserves the admin-defined collection_product.sort_order', function () {
    $collection = Collection::query()->create(['name' => 'Flash Sale', 'slug' => 'flash-sale', 'is_active' => true]);
    $products = attachWithSortOrder($collection, [30, 10, 20]);

    $listing = app(ProductListingService::class);
    $base = $collection->products()->getQuery()->published();
    $result = $listing->paginate($base, new ProductFilterState);

    $ids = $result['products']->pluck('id')->values();

    expect($ids->all())->toBe([
        $products[1]->id, // sort_order 10
        $products[2]->id, // sort_order 20
        $products[0]->id, // sort_order 30
    ]);
});

it('returns no duplicate products when sorted by name', function () {
    $collection = Collection::query()->create(['name' => 'Flash Sale', 'slug' => 'flash-sale', 'is_active' => true]);
    attachWithSortOrder($collection, [1, 2, 3]);

    $listing = app(ProductListingService::class);
    $base = $collection->products()->getQuery()->published();
    $result = $listing->paginate($base, new ProductFilterState(sort: 'name_asc'));

    $ids = $result['products']->pluck('id');

    expect($ids->unique()->count())->toBe($ids->count())
        ->and($ids->count())->toBe(3);
});

it('paginates collection products', function () {
    $collection = Collection::query()->create(['name' => 'Flash Sale', 'slug' => 'flash-sale', 'is_active' => true]);
    attachWithSortOrder($collection, range(1, 6));

    $listing = app(ProductListingService::class);
    $base = $collection->products()->getQuery()->published();

    $pageOne = $listing->paginate($base, new ProductFilterState(page: 1, perPage: 4));
    $pageTwo = $listing->paginate($base, new ProductFilterState(page: 2, perPage: 4));

    expect($pageOne['products']->total())->toBe(6)
        ->and($pageOne['products']->count())->toBe(4)
        ->and($pageTwo['products']->count())->toBe(2)
        ->and($pageOne['products']->pluck('id')->intersect($pageTwo['products']->pluck('id'))->isEmpty())->toBeTrue();
});

it('respects brand filters on the collection listing', function () {
    $brandA = Brand::query()->create(['name' => 'Brand A', 'slug' => 'brand-a']);
    $brandB = Brand::query()->create(['name' => 'Brand B', 'slug' => 'brand-b']);

    $inA = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => $brandA->id]);
    $inB = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => $brandB->id]);
    ProductTranslation::factory()->for($inA)->create(['locale' => 'en', 'name' => 'In A']);
    ProductTranslation::factory()->for($inB)->create(['locale' => 'en', 'name' => 'In B']);

    $collection = Collection::query()->create(['name' => 'Flash Sale', 'slug' => 'flash-sale', 'is_active' => true]);
    $collection->products()->attach([$inA->id => ['sort_order' => 1], $inB->id => ['sort_order' => 2]]);

    $listing = app(ProductListingService::class);
    $base = $collection->products()->getQuery()->published();
    $result = $listing->paginate($base, new ProductFilterState(brandIds: [$brandB->id]));

    expect($result['products']->total())->toBe(1)
        ->and($result['products']->first()->id)->toBe($inB->id);
});
