<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\CouponService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

function makeCartWithCoupon(Tenant $tenant, Coupon $coupon): Cart
{
    // tenant_id/product_id set explicitly — Product/ProductVariant have no
    // tenant() relation method, and this keeps the fixture independent of
    // any relation-name assumption (see CheckoutDoubleSubmissionTest).
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
    ]);

    $cart = Cart::query()->create([
        'tenant_id' => $tenant->id,
        'currency_code' => 'BDT',
        'coupon_id' => $coupon->id,
    ]);

    CartItem::query()->create([
        'tenant_id' => $tenant->id,
        'cart_id' => $cart->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => $variant->price,
    ]);

    return $cart->fresh(['items.variant.product.collections']);
}

/**
 * coupon_redemptions.order_id is a required (non-nullable) foreign key, so
 * simulating "this coupon was already redeemed" needs a real Order row to
 * point at, even though this test isn't exercising order creation itself.
 */
function makeMinimalOrderForRedemption(Tenant $tenant, int $sequence): Order
{
    return Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => "ORD-2026-{$sequence}",
        'status' => 'pending',
        'grand_total' => 1000,
    ]);
}

it('rejects a coupon once its total usage limit is reached, via the locked validation path', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $coupon = Coupon::query()->create([
        'code' => 'LIMIT1',
        'name' => 'Limited Coupon',
        'type' => 'fixed_amount',
        'value' => 1000,
        'usage_limit_total' => 1,
        'is_active' => true,
    ]);

    // Simulate the coupon already having been redeemed once (e.g. by a
    // concurrent checkout that committed first).
    $priorOrder = makeMinimalOrderForRedemption($tenant, 1);
    CouponRedemption::query()->create([
        'coupon_id' => $coupon->id,
        'order_id' => $priorOrder->id,
        'discount_amount' => 1000,
        'redeemed_at' => now(),
    ]);

    $cart = makeCartWithCoupon($tenant, $coupon);

    $result = DB::transaction(fn () => app(CouponService::class)->lockAndComputeForCart($cart, null));

    expect($result->valid)->toBeFalse();

    app(Tenancy::class)->set(null);
});

it('accepts a coupon while under its total usage limit, via the locked validation path', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $coupon = Coupon::query()->create([
        'code' => 'LIMIT2',
        'name' => 'Roomier Coupon',
        'type' => 'fixed_amount',
        'value' => 1000,
        'usage_limit_total' => 2,
        'is_active' => true,
    ]);

    $priorOrder = makeMinimalOrderForRedemption($tenant, 2);
    CouponRedemption::query()->create([
        'coupon_id' => $coupon->id,
        'order_id' => $priorOrder->id,
        'discount_amount' => 1000,
        'redeemed_at' => now(),
    ]);

    $cart = makeCartWithCoupon($tenant, $coupon);

    $result = DB::transaction(fn () => app(CouponService::class)->lockAndComputeForCart($cart, null));

    expect($result->valid)->toBeTrue();

    app(Tenancy::class)->set(null);
});

it('serializes redemption of the same coupon by locking the Coupon row for the transaction', function (): void {
    // Deterministic, non-parallel stand-in for the true concurrent race (Pest
    // runs single-threaded): asserts the SELECT ... FOR UPDATE is actually
    // issued for a coupon-backed cart, which is the mechanism that would
    // block a second, real concurrent transaction until this one commits.
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $coupon = Coupon::query()->create([
        'code' => 'LOCKED',
        'name' => 'Locked Coupon',
        'type' => 'fixed_amount',
        'value' => 500,
        'usage_limit_total' => 5,
        'is_active' => true,
    ]);

    $cart = makeCartWithCoupon($tenant, $coupon);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    DB::transaction(fn () => app(CouponService::class)->lockAndComputeForCart($cart, null));

    $lockingQuery = collect($queries)->first(
        fn (string $sql): bool => str_contains($sql, 'coupons') && str_contains(strtolower($sql), 'for update')
    );

    expect($lockingQuery)->not->toBeNull();

    app(Tenancy::class)->set(null);
});