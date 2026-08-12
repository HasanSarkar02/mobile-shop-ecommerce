<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Support\Str;

function buyNowBaseUrl(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function buyNowProduct(array $variantOverrides = []): array
{
    $tenant = actingAsTenant(['status' => 'active']);

    $product = Product::factory()->create(['status' => 'published']);
    \App\Models\ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create($variantOverrides);

    return [$slug = $product->translation('en')->slug, $variant, $tenant, buyNowBaseUrl($tenant)];
}

it('adds the variant to the cart and redirects straight to checkout', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();
    app(InventoryService::class)->restock($variant, 10);

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])
        ->assertRedirect($base.'/checkout');

    $item = CartItem::query()->first();
    expect($item)->not->toBeNull();
    expect($item->product_variant_id)->toBe($variant->id);
    expect($item->quantity)->toBe(2);
});

it('requires a concrete product_variant_id', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', ['quantity' => 1])
        ->assertSessionHasErrors('product_variant_id');

    expect(CartItem::query()->count())->toBe(0);
});

it('rejects a variant that has no stock', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertStatus(302)
        ->assertSessionHas('error');

    expect(CartItem::query()->count())->toBe(0);
});

it('rejects a discontinued variant', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct(['availability' => 'discontinued']);

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertStatus(302)
        ->assertSessionHas('error');

    expect(CartItem::query()->count())->toBe(0);
});

it('allows a preorder variant according to the existing stock rules', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct([
        'inventory_type' => 'not_tracked',
        'fulfillment_strategy' => 'preorder',
    ]);

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertRedirect($base.'/checkout');

    expect(CartItem::query()->count())->toBe(1);
});

it('allows a backorder-allow variant with zero stock per the existing rules', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct(['backorder_policy' => 'allow']);

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertRedirect($base.'/checkout');

    expect(CartItem::query()->count())->toBe(1);
});

it('keeps guest checkout working end to end', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();
    app(InventoryService::class)->restock($variant, 5);

    // Loading the page issues the guest cart token; forward the returned
    // (already encrypted) cookie just like a real browser would.
    $page = $this->get($base.'/product/'.$slug);
    $token = collect($page->headers->getCookies())
        ->firstWhere(fn ($cookie) => $cookie->getName() === 'cart_token')
        ->getValue();

    $this->withUnencryptedCookie('cart_token', $token)
        ->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertRedirect($base.'/checkout');

    // A guest cart was created (no customer) and carries the variant.
    $cart = Cart::query()->whereNotNull('session_token')->whereNull('customer_id')->first();
    expect($cart)->not->toBeNull();
    expect(CartItem::query()->where('cart_id', $cart->id)->where('product_variant_id', $variant->id)->exists())->toBeTrue();
});

it('respects the requested quantity', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();
    app(InventoryService::class)->restock($variant, 20);

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ])
        ->assertRedirect($base.'/checkout');

    expect(CartItem::query()->first()->quantity)->toBe(3);
});

it('rejects a zero quantity exactly like add to cart', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();
    app(InventoryService::class)->restock($variant, 10);

    $this->from($base.'/product/'.$slug)
        ->post($base.'/buy-now', [
            'product_variant_id' => $variant->id,
            'quantity' => 0,
        ])
        ->assertSessionHasErrors('quantity');

    expect(CartItem::query()->count())->toBe(0);
});

it('does not regress the existing Add to Cart endpoint', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();
    app(InventoryService::class)->restock($variant, 10);

    $page = $this->get($base.'/product/'.$slug);
    $token = collect($page->headers->getCookies())
        ->firstWhere(fn ($cookie) => $cookie->getName() === 'cart_token')
        ->getValue();

    $response = $this->withUnencryptedCookie('cart_token', $token)
        ->from($base.'/product/'.$slug)
        ->post($base.'/cart', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

    // Old behavior: redirect back, not to checkout, with the status flash.
    $response->assertRedirect($base.'/product/'.$slug)
        ->assertSessionHas('status', 'Added to cart.');
    expect(CartItem::query()->first()->product_variant_id)->toBe($variant->id);
    expect(Cart::query()->whereNotNull('session_token')->count())->toBe(1);
});

it('exposes the Buy Now CTA on the product page', function (): void {
    [$slug, $variant, $tenant, $base] = buyNowProduct();
    app(InventoryService::class)->restock($variant, 10);

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('buy-now');
    expect($html)->toContain('Buy Now');
});