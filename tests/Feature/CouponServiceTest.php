<?php

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Services\CouponService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    actingAsTenant();
});

it('computes a percentage discount capped by the maximum discount amount', function () {
    [$cart] = createCartWithVariant(2);
    Coupon::query()->create([
        'name' => 'Half off', 'code' => 'HALF50', 'type' => 'percentage', 'value' => 50,
        'max_discount_amount' => 50000, 'is_active' => true,
    ]);

    $result = app(CouponService::class)->applyToCart($cart, 'half50', null);

    expect($result->valid)->toBeTrue();
    expect($result->discountAmount)->toBe(50000);
});

it('rejects a coupon below the minimum order amount', function () {
    [$cart] = createCartWithVariant(1);
    Coupon::query()->create([
        'name' => 'Big spender', 'code' => 'BIG500', 'type' => 'fixed_amount', 'value' => 10000,
        'min_order_amount' => 999999999, 'is_active' => true,
    ]);

    $result = app(CouponService::class)->applyToCart($cart, 'BIG500', null);

    expect($result->valid)->toBeFalse();
});

it('enforces total usage limits', function () {
    [$cartOne] = createCartWithVariant(1);
    Coupon::query()->create([
        'name' => 'Limited', 'code' => 'LIMIT1', 'type' => 'fixed_amount', 'value' => 5000,
        'usage_limit_total' => 1, 'is_active' => true,
    ]);

    app(CouponService::class)->applyToCart($cartOne, 'LIMIT1', null);
    app(OrderService::class)->createFromCart($cartOne, [
        'guest_name' => 'A', 'guest_email' => 'a@example.com', 'guest_phone' => '01700000000',
    ]);

    [$cartTwo] = createCartWithVariant(1);
    $result = app(CouponService::class)->applyToCart($cartTwo, 'LIMIT1', null);

    expect($result->valid)->toBeFalse();
    expect(CouponRedemption::query()->count())->toBe(1);
});

it('frees up coupon usage when the order is cancelled', function () {
    $customer = Customer::factory()->create();
    [$cart] = createCartWithVariant(1, $customer);
    $cart->update(['customer_id' => $customer->id]);
    $coupon = Coupon::query()->create([
        'name' => 'Once each', 'code' => 'ONCE', 'type' => 'fixed_amount', 'value' => 2000,
        'usage_limit_per_customer' => 1, 'is_active' => true,
    ]);

    app(CouponService::class)->applyToCart($cart, 'ONCE', $customer);
    $order = app(OrderService::class)->createFromCart($cart, [], OrderSource::Website);

    expect(CouponRedemption::query()->where('coupon_id', $coupon->id)->count())->toBe(1);

    app(OrderService::class)->updateStatus($order, OrderStatus::Cancelled);

    expect(CouponRedemption::query()->where('coupon_id', $coupon->id)->count())->toBe(0);

    [$cartTwo] = createCartWithVariant(1, $customer);
    $cartTwo->update(['customer_id' => $customer->id]);
    $result = app(CouponService::class)->applyToCart($cartTwo, 'ONCE', $customer);

    expect($result->valid)->toBeTrue();
});

it('rejects first-order-only coupons for repeat customers', function () {
    $customer = Customer::factory()->create();
    [$priorCart] = createCartWithVariant(1, $customer);
    $priorCart->update(['customer_id' => $customer->id]);
    $priorOrder = app(OrderService::class)->createFromCart($priorCart, []);
    app(OrderService::class)->updateStatus($priorOrder, OrderStatus::Confirmed);

    [$cart] = createCartWithVariant(1, $customer);
    $cart->update(['customer_id' => $customer->id]);
    Coupon::query()->create([
        'name' => 'Welcome', 'code' => 'WELCOME', 'type' => 'fixed_amount', 'value' => 3000,
        'customer_eligibility' => 'first_order_only', 'is_active' => true,
    ]);

    $result = app(CouponService::class)->applyToCart($cart, 'WELCOME', $customer);

    expect($result->valid)->toBeFalse();
});