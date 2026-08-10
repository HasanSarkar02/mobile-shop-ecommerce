<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class CartService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderService $orders,
        private readonly CouponService $coupons,
    ) {
    }

    public function getOrCreateCart(?Customer $customer, ?string $cartToken): Cart
    {
        $query = Cart::query()->whereNull('converted_at');

        $cart = $customer
            ? (clone $query)->where('customer_id', $customer->id)->first()
            : (clone $query)->where('session_token', $cartToken)->first();

        return $cart ?? Cart::query()->create([
            'tenant_id' => tenant()->id,
            'customer_id' => $customer?->id,
            'session_token' => $customer ? null : $cartToken,
            'currency_code' => tenant()->currency,
        ]);
    }

    public function addItem(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        if (! $this->inventory->isPurchasable($variant, $quantity)) {
            throw new \RuntimeException("'{$variant->sku}' is not available in the requested quantity.");
        }

        $item = $cart->items()->where('product_variant_id', $variant->id)->first();

        if ($item) {
            $item->update(['quantity' => $item->quantity + $quantity, 'unit_price' => $variant->price]);

            return $item;
        }

        return $cart->items()->create([
            'tenant_id' => $cart->tenant_id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $variant->price,
        ]);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function markConverted(Cart $cart): void
    {
        $cart->update(['converted_at' => now()]);
    }

    public function mergeGuestCartIntoCustomer(string $cartToken, Customer $customer): void
    {
        $guestCart = Cart::query()
            ->where('session_token', $cartToken)
            ->whereNull('customer_id')
            ->whereNull('converted_at')
            ->first();

        if (! $guestCart) {
            return;
        }

        $customerCart = $this->getOrCreateCart($customer, null);

        foreach ($guestCart->items as $guestItem) {
            $existing = $customerCart->items()->where('product_variant_id', $guestItem->product_variant_id)->first();

            // Documented rule: authenticated cart wins on conflicting lines, guest-only lines are appended.
            if (! $existing) {
                $customerCart->items()->create([
                    'tenant_id' => $customerCart->tenant_id,
                    'product_variant_id' => $guestItem->product_variant_id,
                    'quantity' => $guestItem->quantity,
                    'unit_price' => $guestItem->product_variant_id
                        ? (ProductVariant::find($guestItem->product_variant_id)?->price ?? $guestItem->unit_price)
                        : $guestItem->unit_price,
                ]);
            }
        }

        $guestCart->delete();
    }

    /**
     * Re-validates every line against current price and purchasability.
     * Never trust the cart's snapshotted price/availability at checkout time.
     * Opportunistically releases any expired reservation touching these variants first.
     *
     * @return array{issues: Collection, priceChanged: bool}
     */
    public function revalidate(Cart $cart): array
    {
        $issues = collect();
        $priceChanged = false;

        foreach ($cart->items()->with('variant')->get() as $item) {
            if (! $item->variant) {
                $item->delete();
                $issues->push("An item in your cart is no longer available and was removed.");

                continue;
            }

            $this->orders->releaseExpiredReservations($item->variant);

            if (! $this->inventory->isPurchasable($item->variant, $item->quantity)) {
                $available = $this->inventory->availableQuantity($item->variant);

                if ($available <= 0) {
                    $item->delete();
                    $issues->push("'{$item->variant->sku}' is no longer in stock and was removed from your cart.");
                } else {
                    $item->update(['quantity' => $available]);
                    $issues->push("Only {$available} of '{$item->variant->sku}' left — quantity adjusted.");
                }

                continue;
            }

            if ($item->unit_price !== $item->variant->price) {
                $item->update(['unit_price' => $item->variant->price]);
                $priceChanged = true;
                $issues->push("The price of '{$item->variant->sku}' has changed.");
            }
        }

        if ($cart->coupon_id) {
            $couponResult = $this->coupons->computeForCart($cart, $cart->customer);

            if (! $couponResult->valid) {
                $cart->update(['coupon_id' => null]);
                $issues->push($couponResult->message ?? 'Your coupon is no longer valid and was removed.');
            }
        }

        return ['issues' => $issues, 'priceChanged' => $priceChanged];
    }
}