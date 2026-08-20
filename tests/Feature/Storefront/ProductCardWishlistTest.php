<?php

declare(strict_types=1);

use App\Enums\FulfillmentStrategy;
use App\Enums\ProductStatus;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\InventoryService;
use App\Services\Storefront\ProductCardData;
use App\Services\Storefront\ProductListingService;
use App\Support\ProductFilterState;
use Illuminate\Support\Facades\DB;

function cardBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function cardProduct(array $productOverrides = [], array $variantOverrides = []): Product
{
    $product = Product::factory()->create(array_merge(['status' => ProductStatus::Published], $productOverrides));
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create($variantOverrides);
    app(InventoryService::class)->restock($variant, 10);

    return $product;
}

function cardView(Product $product, ?array $wishlistedIds = []): array
{
    $product->load('translations', 'variants', 'media', 'emiPlans');

    return app(ProductCardData::class)->forMany(collect([$product]), collect($wishlistedIds))->first();
}

it('resolves the cheapest ACTIVE purchasable variant for the card', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    // Cheapest variant is inactive — the card must not show it.
    $cheap = ProductVariant::factory()->for($product)->create([
        'price' => 1000000,
        'is_active' => false,
    ]);
    // Dearest variant is active and purchasable.
    $active = ProductVariant::factory()->for($product)->create([
        'price' => 2000000,
        'is_active' => true,
    ]);
    app(InventoryService::class)->restock($active, 10);

    $product->load('translations', 'variants', 'media', 'emiPlans');
    $card = app(ProductCardData::class)->forMany(collect([$product]), collect())->first();

    expect($card['variant']['id'])->toBe($active->id);
    expect($card['variant']['price'])->toBe(2000000);
    expect($cheap->id)->not->toBe($card['variant']['id']);
});

it('prefers a purchasable variant over the cheapest out-of-stock one', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    // Cheapest variant is tracked and out of stock (backorder denied).
    $outOfStock = ProductVariant::factory()->for($product)->create([
        'price' => 1000000,
        'inventory_type' => 'tracked',
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'deny',
    ]);
    // Slightly pricier but purchasable.
    $available = ProductVariant::factory()->for($product)->create([
        'price' => 1500000,
        'inventory_type' => 'tracked',
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'deny',
    ]);
    app(InventoryService::class)->restock($available, 10);

    $product->load('translations', 'variants', 'media', 'emiPlans');
    $card = app(ProductCardData::class)->forMany(collect([$product]), collect())->first();

    expect($card['variant']['id'])->toBe($available->id);
    expect($card['variant']['is_out_of_stock'])->toBeFalse();
});

it('marks the card out of stock when the only active variant cannot be purchased', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);

    $variant = ProductVariant::factory()->for($product)->create([
        'inventory_type' => 'tracked',
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'backorder_policy' => 'deny',
    ]);

    $product->load('translations', 'variants', 'media', 'emiPlans');
    $card = app(ProductCardData::class)->forMany(collect([$product]), collect())->first();

    expect($card['variant']['is_out_of_stock'])->toBeTrue();
});

it('builds a card without a variant when the product has no active variants', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['is_active' => false]);

    $product->load('translations', 'variants', 'media', 'emiPlans');
    $card = app(ProductCardData::class)->forMany(collect([$product]), collect())->first();

    expect($card['variant'])->toBeNull();
});

it('computes the discount percentage from the chosen variant', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    ProductVariant::factory()->for($product)->create([
        'price' => 800000,
        'compare_at_price' => 1000000,
    ]);

    $product->load('translations', 'variants', 'media', 'emiPlans');
    $card = app(ProductCardData::class)->forMany(collect([$product]), collect())->first();

    expect($card['discount_percentage'])->toBe(20);
});

it('flags official imports and pre-order variants on the card', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct(['is_official_import' => true], [
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
    ]);
    $card = cardView($product);

    expect($card['is_official_import'])->toBeTrue();
    expect($card['variant']['is_preorder'])->toBeTrue();
});

it('reflects wishlist membership from the passed wishlist ids', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct();
    $other = cardProduct();

    $card = cardView($product, [$product->id]);

    expect($card['wishlisted'])->toBeTrue();

    $otherCard = cardView($other, [$product->id]);
    expect($otherCard['wishlisted'])->toBeFalse();
});

it('renders the product card with a single discount badge', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct([], ['price' => 800000, 'compare_at_price' => 1000000]);
    $base = cardBase($tenant);
    $slug = $product->translation('en')->slug;

    // Product Card partial — the source of truth for card markup.
    $html = view('storefront.partials.product-card', ['card' => cardView($product)])->render();

    expect(substr_count($html, '% OFF'))->toBe(1);
});

it('renders the image fallback when the product has no image', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct();

    $html = view('storefront.partials.product-card', ['card' => cardView($product)])->render();

    expect($html)->toContain('M2.25 15.75l5.159-5.159');
});

it('seeds the wishlist store correctly on the wishlist page (SSR initial state)', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct();
    $base = cardBase($tenant);

    $wishlist = Wishlist::query()->create([
        'tenant_id' => tenant()->id,
        'guest_token' => 'guest-card-test',
        'name' => 'My Wishlist',
        'is_default' => true,
    ]);
    WishlistItem::query()->create([
        'tenant_id' => tenant()->id,
        'wishlist_id' => $wishlist->id,
        'product_id' => $product->id,
    ]);

    $html = $this->withCookie('wishlist_token', 'guest-card-test')
        ->get($base.'/wishlist')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('seed('.$product->id.', true)');
});

it('seeds the wishlist store as not-wishlisted on the collection listing', function (): void {
    $tenant = actingAsTenant(['subdomain' => 'demo', 'status' => 'active']);
    $product = cardProduct();
    $base = cardBase($tenant);

    $collection = Collection::query()->create([
        'name' => 'Card Test',
        'slug' => 'card-test',
        'is_active' => true,
    ]);
    $collection->products()->attach([$product->id => ['sort_order' => 1]]);

    $html = $this->get($base.'/collection/card-test')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('seed('.$product->id.', false)');
});

it('returns the resolved wishlist state from the JSON toggle endpoint', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct();
    $base = cardBase($tenant);

    $this->withCookie('wishlist_token', 'guest-json-toggle')
        ->postJson($base.'/wishlist/toggle', ['product_id' => $product->id])
        ->assertOk()
        ->assertJson(['wishlisted' => true]);

    $this->withCookie('wishlist_token', 'guest-json-toggle')
        ->postJson($base.'/wishlist/toggle', ['product_id' => $product->id])
        ->assertOk()
        ->assertJson(['wishlisted' => false]);
});

it('keeps the redirect + flash behaviour for non-JSON wishlist toggles', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct();
    $base = cardBase($tenant);

    $this->withCookie('wishlist_token', 'guest-flash-toggle')
        ->post($base.'/wishlist/toggle', ['product_id' => $product->id])
        ->assertRedirect()
        ->assertSessionHas('status');
});

it('does not persist a wishlist change for an unknown product', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $base = cardBase($tenant);

    $this->withCookie('wishlist_token', 'guest-unknown-product')
        ->postJson($base.'/wishlist/toggle', ['product_id' => 999999])
        ->assertStatus(404);

    expect(WishlistItem::query()->count())->toBe(0);
});

it('never leaks wishlist items across tenants', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    $product = cardProduct();
    $base = cardBase($tenant);

    $otherTenant = Tenant::factory()->create(['status' => 'active']);
    $foreign = Product::factory()->create([
        'status' => ProductStatus::Published,
        'tenant_id' => $otherTenant->id,
    ]);
    ProductTranslation::factory()->for($foreign)->create(['locale' => 'en']);

    $wishlist = Wishlist::query()->create([
        'tenant_id' => tenant()->id,
        'guest_token' => 'guest-tenant-isolation',
        'name' => 'My Wishlist',
        'is_default' => true,
    ]);
    WishlistItem::query()->create([
        'tenant_id' => tenant()->id,
        'wishlist_id' => $wishlist->id,
        'product_id' => $product->id,
    ]);

    $html = $this->withCookie('wishlist_token', 'guest-tenant-isolation')
        ->get($base.'/wishlist')
        ->assertOk()
        ->getContent();

    expect($html)->toContain($product->translation('en')->name);
    expect($html)->not->toContain($foreign->translation('en')->name);
});

it('eager-loads card relations so the catalog grid issues no per-card queries', function (): void {
    $tenant = actingAsTenant(['status' => 'active']);
    foreach (range(1, 3) as $i) {
        cardProduct();
    }

    $listing = app(ProductListingService::class);
    $result = $listing->paginate(Product::query()->published(), new ProductFilterState);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(ProductCardData::class)->forMany($result['products'], collect());

    // Relations are loaded in the single paginate query — no lazy per-product
    // loads for translations/variants/media/emiPlans may follow.
    expect(collect($queries)->filter(
        fn (string $sql) => str_contains($sql, '`variants`')
            || str_contains($sql, '`media`')
            || str_contains($sql, '`product_translations`')
            || str_contains($sql, '`emi_plans`'),
    ))->toHaveCount(0);
});
