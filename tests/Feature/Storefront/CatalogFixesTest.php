<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\InventoryService;
use App\Services\Storefront\ProductListingService;
use App\Support\ProductFilterState;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function fixTenant(string $subdomain): Tenant
{
    return actingAsTenant(['subdomain' => $subdomain, 'status' => 'active']);
}

function fixBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function fixProduct(Tenant $tenant, ?Brand $brand = null, string $name = 'Fix Product'): Product
{
    app(Tenancy::class)->set($tenant);

    $product = Product::factory()->create(['status' => 'published', 'brand_id' => $brand?->id]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en', 'name' => $name]);

    return $product;
}

function fixColorFacet(): AttributeDefinition
{
    app(Tenancy::class)->set(tenant());

    return AttributeDefinition::query()->create([
        'code' => 'color',
        'label' => 'Color',
        'data_type' => 'select',
        'is_filterable' => true,
        'is_variant_defining' => true,
    ]);
}

it('keeps other brands visible in facets when one brand is selected', function (): void {
    $tenant = fixTenant('fix-brands');
    $apple = Brand::query()->create(['name' => 'Apple', 'slug' => 'apple']);
    $samsung = Brand::query()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    fixProduct($tenant, $apple, 'Apple Phone');
    fixProduct($tenant, $samsung, 'Samsung Phone');

    $filters = new ProductFilterState(brandIds: [$apple->id]);
    $result = app(ProductListingService::class)->paginate(Product::query()->published(), $filters);

    $brandNames = $result['facets']['brands']->pluck('name')->all();

    expect($result['products']->count())->toBe(1)
        ->and(count($brandNames))->toBe(2)
        ->and($brandNames)->toContain('Apple', 'Samsung')
        ->and($result['facets']['brands']->firstWhere('name', 'Samsung')->products_count)->toBe(1);
});

it('keeps unselected attribute options visible when one value is selected', function (): void {
    $tenant = fixTenant('fix-attr');
    $color = fixColorFacet();
    $red = $color->options()->create(['value' => 'red', 'label' => 'Red']);
    $blue = $color->options()->create(['value' => 'blue', 'label' => 'Blue']);

    $redOnly = fixProduct($tenant, name: 'Red Item');
    $variantA = ProductVariant::factory()->for($redOnly)->create();
    $variantA->attributeValues()->create([
        'product_id' => $redOnly->id,
        'attribute_definition_id' => $color->id,
        'attribute_option_id' => $red->id,
    ]);

    $blueOnly = fixProduct($tenant, name: 'Blue Item');
    $variantB = ProductVariant::factory()->for($blueOnly)->create();
    $variantB->attributeValues()->create([
        'product_id' => $blueOnly->id,
        'attribute_definition_id' => $color->id,
        'attribute_option_id' => $blue->id,
    ]);

    $filters = new ProductFilterState(attributes: ['color' => ['Red']]);
    $result = app(ProductListingService::class)->paginate(Product::query()->published(), $filters);

    $options = collect($result['facets']['attributes']['color']['options'] ?? [])->keyBy('value');

    expect($result['products']->count())->toBe(1)
        ->and($options->has('Red'))->toBeTrue()
        ->and($options->has('Blue'))->toBeTrue()
        ->and($options['Blue']['count'])->toBe(1);
});

it('still respects other dimensions while excluding a facet own dimension', function (): void {
    $tenant = fixTenant('fix-mixed');
    $apple = Brand::query()->create(['name' => 'Apple', 'slug' => 'apple']);
    $color = fixColorFacet();
    $red = $color->options()->create(['value' => 'red', 'label' => 'Red']);
    $blue = $color->options()->create(['value' => 'blue', 'label' => 'Blue']);

    $appleRed = fixProduct($tenant, $apple, 'Apple Red');
    $v1 = ProductVariant::factory()->for($appleRed)->create();
    $v1->attributeValues()->create([
        'product_id' => $appleRed->id,
        'attribute_definition_id' => $color->id,
        'attribute_option_id' => $red->id,
    ]);

    $samsungBlue = fixProduct($tenant, Brand::query()->create(['name' => 'Samsung', 'slug' => 'samsung']), 'Samsung Blue');
    $v2 = ProductVariant::factory()->for($samsungBlue)->create();
    $v2->attributeValues()->create([
        'product_id' => $samsungBlue->id,
        'attribute_definition_id' => $color->id,
        'attribute_option_id' => $blue->id,
    ]);

    // Brand=Apple + Color=Red → only the Apple red product; the color facet
    // excludes its own dimension (Red stays listed) but the OTHER dimension
    // (brand=Apple) still narrows it, so Blue disappears entirely.
    $filters = new ProductFilterState(
        brandIds: [$apple->id],
        attributes: ['color' => ['Red']],
    );
    $result = app(ProductListingService::class)->paginate(Product::query()->published(), $filters);

    $optionValues = collect($result['facets']['attributes']['color']['options'] ?? [])->pluck('value')->all();

    expect($result['products']->count())->toBe(1)
        ->and($optionValues)->toBe(['Red']);
});

it('ignores negative zero and non numeric price inputs', function (): void {
    expect(ProductFilterState::priceFromInput('-5'))->toBeNull()
        ->and(ProductFilterState::priceFromInput('abc'))->toBeNull()
        ->and(ProductFilterState::priceFromInput('0'))->toBeNull()
        ->and(ProductFilterState::priceFromInput(''))->toBeNull()
        ->and(ProductFilterState::priceFromInput(null))->toBeNull()
        ->and(ProductFilterState::priceFromInput('19.99'))->toBe(1999)
        ->and(ProductFilterState::priceFromInput('10'))->toBe(1000);
});

it('renders real wishlist aware cards on the pre order page', function (): void {
    $tenant = fixTenant('fix-preorder');
    $product = fixProduct($tenant, name: 'Reserve Me');
    ProductVariant::factory()->for($product)->create([
        'fulfillment_strategy' => 'preorder',
        'expected_available_at' => now()->addMonth(),
    ]);

    $html = get(fixBaseUrl($tenant).'/pre-order')->assertOk()->getContent();

    expect($html)->toContain('Reserve Me')
        ->and($html)->toContain("\$store.wishlist.seed({$product->id}")
        ->and($html)->not->toContain('$store.wishlist.seed(,');
});

it('returns a json failure instead of a false success when the cart cannot add', function (): void {
    $tenant = fixTenant('fix-cart-json');
    $product = fixProduct($tenant, name: 'Serial Gadget');
    $variant = ProductVariant::factory()->for($product)->create(['inventory_type' => 'serialized']);

    $page = get(fixBaseUrl($tenant).'/product/'.$product->translation('en')->slug);
    $token = collect($page->headers->getCookies())
        ->firstWhere(fn ($cookie) => $cookie->getName() === 'cart_token')
        ->getValue();

    // Serialized variant with zero available serials: previously this produced
    // a followed redirect and a false "Added to cart" toast.
    $this->withUnencryptedCookie('cart_token', $token)
        ->postJson(fixBaseUrl($tenant).'/cart', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect(CartItem::query()->count())->toBe(0);
});

it('keeps the redirect behaviour for non json cart failures', function (): void {
    $tenant = fixTenant('fix-cart-redirect');
    $product = fixProduct($tenant, name: 'Out of Stock Thing');
    $variant = ProductVariant::factory()->for($product)->create();

    post(fixBaseUrl($tenant).'/cart', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertStatus(302)->assertSessionHas('error');

    expect(CartItem::query()->count())->toBe(0);
});

it('rejects request quantities above the hard cap server side', function (): void {
    $tenant = fixTenant('fix-qty');
    $product = fixProduct($tenant, name: 'Bulk Item');
    $variant = ProductVariant::factory()->for($product)->create();
    app(InventoryService::class)->restock($variant, 500);

    $page = get(fixBaseUrl($tenant).'/product/'.$product->translation('en')->slug);
    $token = collect($page->headers->getCookies())
        ->firstWhere(fn ($cookie) => $cookie->getName() === 'cart_token')
        ->getValue();

    $this->withUnencryptedCookie('cart_token', $token)
        ->post(fixBaseUrl($tenant).'/cart', [
            'product_variant_id' => $variant->id,
            'quantity' => 150,
        ])
        ->assertSessionHasErrors('quantity');

    expect(CartItem::query()->count())->toBe(0);
});

it('marks a serialized variant without available serials unpurchasable on the pdp', function (): void {
    $tenant = fixTenant('fix-pdp-serial');
    $product = fixProduct($tenant, name: 'Serial Phone');
    ProductVariant::factory()->for($product)->create(['inventory_type' => 'serialized']);

    $html = get(fixBaseUrl($tenant).'/product/'.$product->translation('en')->slug)
        ->assertOk()
        ->getContent();

    // @js() embeds the variants JSON inside an HTML attribute using \u0022
    // escapes for quotes.
    expect($html)->toContain('purchasable\u0022:false');
});

it('gives the cross sell rail its own heading on the product page', function (): void {
    $tenant = fixTenant('fix-crosssell');
    $product = fixProduct($tenant, name: 'Solo Gadget');
    ProductVariant::factory()->for($product)->create();

    $companion = fixProduct($tenant, name: 'Companion Gadget');
    ProductVariant::factory()->for($companion)->create();
    DB::table('product_relations')->insert([
        ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'related_product_id' => $companion->id, 'type' => 'related'],
        ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'related_product_id' => $companion->id, 'type' => 'cross_sell'],
    ]);

    $html = get(fixBaseUrl($tenant).'/product/'.$product->translation('en')->slug)
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Complete Your Setup')
        ->and(substr_count($html, 'You May Also Like'))->toBe(1);
});
