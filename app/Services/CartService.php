<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderService $orders,
        private readonly CouponService $coupons,
    ) {}

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

    public function addItem(Cart $cart, ProductVariant $variant, int|float|string $quantity): CartItem
    {
        $qty = $this->normalizeQty($quantity);
        $this->validateSellByUnit($variant, $qty);

        if (! $this->inventory->isPurchasable($variant, $qty)) {
            throw new \RuntimeException("'{$variant->sku}' is not available in the requested quantity.");
        }

        $item = $cart->items()->where('product_variant_id', $variant->id)->first();

        if ($item) {
            $newQty = bcadd((string) $item->quantity, $qty, 3);
            $this->validateSellByUnit($variant, $newQty);
            $item->update(['quantity' => $newQty, 'unit_price' => $variant->price]);

            return $item;
        }

        return $cart->items()->create([
            'tenant_id' => $cart->tenant_id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price' => $variant->price,
        ]);
    }

    public function updateQuantity(CartItem $item, int|float|string $quantity): void
    {
        $qty = $this->normalizeQty($quantity);

        if (bccomp($qty, '0', 3) !== 1) {
            $item->delete();

            return;
        }

        $item->loadMissing('variant.product');
        $variant = $item->variant;
        if ($variant instanceof ProductVariant) {
            $this->validateSellByUnit($variant, $qty);
        }

        $item->update(['quantity' => $qty]);
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
                $issues->push('An item in your cart is no longer available and was removed.');

                continue;
            }

            $this->orders->releaseExpiredReservations($item->variant);

            if (! $this->inventory->isPurchasable($item->variant, $item->quantity)) {
                $available = $this->inventory->availableDecimal($item->variant);

                if (bccomp($available, '0', 3) !== 1) {
                    $item->delete();
                    $issues->push("'{$item->variant->sku}' is no longer in stock and was removed from your cart.");
                } else {
                    // Clamp to available and also snap to valid sell_by_unit multiple if needed
                    $clamped = $this->clampToSellByUnit($item->variant, $available);
                    $item->update(['quantity' => $clamped]);
                    $issues->push("Only {$clamped} of '{$item->variant->sku}' left — quantity adjusted.");
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

    private function normalizeQty(int|float|string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw new \InvalidArgumentException('Quantity must be numeric.');
        }

        $qty = number_format((float) $quantity, 3, '.', '');

        if (bccomp($qty, '0', 3) !== 1) {
            // Zero or negative is handled by callers (delete), but keep
            // normalization strict for positive quantities — must be >0.000
            // and at most 99.999 for cart.
            if (bccomp($qty, '0', 3) === 0) {
                return '0.000';
            }
        }

        return $qty;
    }

    private function validateSellByUnit(ProductVariant $variant, string $qty): void
    {
        $variant->load('product');

        /** @var mixed $step */
        $step = $variant->product?->sell_by_unit;

        if (! filled($step) || bccomp((string) $step, '0', 3) !== 1) {
            return;
        }

        $stepStr = number_format((float) $step, 3, '.', '');

        // bcmod at scale 3: remainder must be 0.000
        $remainder = bcmod($qty, $stepStr, 3);

        if (bccomp($remainder, '0', 3) !== 0) {
            throw ValidationException::withMessages([
                'quantity' => ["Quantity must be a multiple of {$stepStr}."],
            ]);
        }
    }

    private function clampToSellByUnit(ProductVariant $variant, string $available): string
    {
        $variant->load('product');
        /** @var mixed $step */
        $step = $variant->product?->sell_by_unit;

        if (! filled($step) || bccomp((string) $step, '0', 3) !== 1) {
            return $available;
        }

        $stepStr = number_format((float) $step, 3, '.', '');
        $remainder = bcmod($available, $stepStr, 3);

        if (bccomp($remainder, '0', 3) === 0) {
            return $available;
        }

        // Floor to nearest valid step: available - remainder
        $clamped = bcsub($available, $remainder, 3);

        return bccomp($clamped, '0', 3) === 1 ? $clamped : $stepStr;
    }
}
