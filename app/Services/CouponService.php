<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CouponCustomerEligibility;
use App\Enums\CouponEligibilityScope;
use App\Enums\CouponScopeMode;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Support\CouponValidationResult;
use Illuminate\Support\Collection as EloquentCollection;

/**
 * Single source of truth for coupon eligibility, discount computation, and
 * redemption tracking. No other code path applies or computes a discount.
 */
class CouponService
{
    public function applyToCart(Cart $cart, string $code, ?Customer $customer): CouponValidationResult
    {
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            return CouponValidationResult::invalid('Invalid coupon code.');
        }

        $result = $this->validateAndCompute($coupon, $cart, $customer);

        if ($result->valid) {
            $cart->update(['coupon_id' => $coupon->id]);
        }

        return $result;
    }

    public function removeFromCart(Cart $cart): void
    {
        $cart->update(['coupon_id' => null]);
    }

    public function computeForCart(Cart $cart, ?Customer $customer): CouponValidationResult
    {
        if (! $cart->coupon_id) {
            return CouponValidationResult::none();
        }

        $coupon = Coupon::query()->find($cart->coupon_id);

        if (! $coupon) {
            return CouponValidationResult::none();
        }

        return $this->validateAndCompute($coupon, $cart, $customer);
    }

    /**
     * Same as computeForCart(), but locks the Coupon row for the remainder of
     * the caller's transaction before validating usage limits. Must be called
     * from inside the DB::transaction() that will also create the
     * CouponRedemption row (OrderService::createFromCart()) so concurrent
     * checkouts racing for the same coupon serialize on this lock: the second
     * transaction's withinUsageLimits() COUNT only runs after the first has
     * committed (or rolled back), so it sees an accurate count instead of a
     * stale one. This preserves the exact validation rules of
     * validateAndCompute(); it only makes the usage-limit check race-safe.
     */
    public function lockAndComputeForCart(Cart $cart, ?Customer $customer): CouponValidationResult
    {
        if (! $cart->coupon_id) {
            return CouponValidationResult::none();
        }

        $coupon = Coupon::query()->whereKey($cart->coupon_id)->lockForUpdate()->first();

        if (! $coupon) {
            return CouponValidationResult::none();
        }

        return $this->validateAndCompute($coupon, $cart, $customer);
    }

    public function recordRedemption(Order $order, Cart $cart, ?Customer $customer, int $discountAmount): void
    {
        if (! $cart->coupon_id || $discountAmount <= 0) {
            return;
        }

        CouponRedemption::query()->create([
            'tenant_id' => $order->tenant_id,
            'coupon_id' => $cart->coupon_id,
            'order_id' => $order->id,
            'customer_id' => $customer?->id,
            'discount_amount' => $discountAmount,
            'redeemed_at' => now(),
        ]);
    }

    public function releaseForOrder(Order $order): void
    {
        CouponRedemption::query()->where('order_id', $order->id)->delete();
    }

    public function validateAndCompute(Coupon $coupon, Cart $cart, ?Customer $customer): CouponValidationResult
    {
        if (! $coupon->isCurrentlyActive()) {
            return CouponValidationResult::invalid('This coupon is no longer valid.');
        }

        $cart->loadMissing('items.variant.product.collections');

        $eligibleItems = $this->eligibleCartItems($coupon, $cart);

        if ($eligibleItems->isEmpty()) {
            return CouponValidationResult::invalid('This coupon does not apply to the items in your cart.');
        }

        $eligibleSubtotal = $eligibleItems->sum(fn ($item) => $item->lineTotal());
        $cartSubtotal = $cart->items->sum(fn ($item) => $item->lineTotal());
        $cartQuantity = $cart->items->sum('quantity');

        if ($coupon->min_order_amount && $cartSubtotal < $coupon->min_order_amount) {
            return CouponValidationResult::invalid('Your order does not meet the minimum amount for this coupon.');
        }

        if ($coupon->min_quantity && $cartQuantity < $coupon->min_quantity) {
            return CouponValidationResult::invalid('Your order does not meet the minimum quantity for this coupon.');
        }

        if (! $this->customerEligible($coupon, $customer)) {
            return CouponValidationResult::invalid('You are not eligible to use this coupon.');
        }

        if (! $this->withinUsageLimits($coupon, $customer)) {
            return CouponValidationResult::invalid('This coupon has reached its usage limit.');
        }

        $discount = match ($coupon->type) {
            CouponType::Percentage => (int) round($eligibleSubtotal * ($coupon->value / 100)),
            CouponType::FixedAmount => min($coupon->value, $eligibleSubtotal),
            CouponType::FreeShipping => 0,
        };

        if ($coupon->max_discount_amount) {
            $discount = min($discount, $coupon->max_discount_amount);
        }

        return CouponValidationResult::valid($discount, $coupon->type === CouponType::FreeShipping);
    }

    private function eligibleCartItems(Coupon $coupon, Cart $cart): EloquentCollection
    {
        if ($coupon->eligibility_scope === CouponEligibilityScope::All) {
            return $cart->items;
        }

        $scopes = $coupon->scopes;
        $productIds = $scopes->where('scopable_type', Product::class)->pluck('scopable_id');
        $categoryIds = $scopes->where('scopable_type', Category::class)->pluck('scopable_id');
        $brandIds = $scopes->where('scopable_type', Brand::class)->pluck('scopable_id');
        $collectionIds = $scopes->where('scopable_type', Collection::class)->pluck('scopable_id');

        $matches = $cart->items->filter(function ($item) use ($productIds, $categoryIds, $brandIds, $collectionIds) {
            $product = $item->variant->product;

            return $productIds->contains($product->id)
                || $categoryIds->contains($product->category_id)
                || $brandIds->contains($product->brand_id)
                || $product->collections->pluck('id')->intersect($collectionIds)->isNotEmpty();
        });

        return $coupon->scope_mode === CouponScopeMode::Exclude
            ? $cart->items->diff($matches)
            : $matches;
    }

    private function customerEligible(Coupon $coupon, ?Customer $customer): bool
    {
        return match ($coupon->customer_eligibility) {
            CouponCustomerEligibility::All => true,
            CouponCustomerEligibility::FirstOrderOnly => ! $customer || ! Order::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered])
                ->exists(),
            CouponCustomerEligibility::SpecificCustomers => $customer !== null
                && $coupon->customerEligibilities()->where('customer_id', $customer->id)->exists(),
        };
    }

    private function withinUsageLimits(Coupon $coupon, ?Customer $customer): bool
    {
        if ($coupon->usage_limit_total !== null
            && CouponRedemption::query()->where('coupon_id', $coupon->id)->count() >= $coupon->usage_limit_total) {
            return false;
        }

        if ($coupon->usage_limit_per_customer !== null && $customer
            && CouponRedemption::query()->where('coupon_id', $coupon->id)->where('customer_id', $customer->id)->count() >= $coupon->usage_limit_per_customer) {
            return false;
        }

        return true;
    }
}
