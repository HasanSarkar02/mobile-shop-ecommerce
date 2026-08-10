<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Wishlist;

class WishlistService
{
    public function getOrCreateWishlist(?Customer $customer, ?string $guestToken): Wishlist
    {
        $query = Wishlist::query()->where('is_default', true);

        $wishlist = $customer
            ? (clone $query)->where('customer_id', $customer->id)->first()
            : (clone $query)->where('guest_token', $guestToken)->first();

        return $wishlist ?? Wishlist::query()->create([
            'tenant_id' => tenant()->id,
            'customer_id' => $customer?->id,
            'guest_token' => $customer ? null : $guestToken,
            'name' => 'My Wishlist',
            'is_default' => true,
        ]);
    }

    public function toggle(Wishlist $wishlist, Product $product, ?int $variantId = null): bool
    {
        $existing = $wishlist->items()->where('product_id', $product->id)->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $wishlist->items()->create([
            'tenant_id' => $wishlist->tenant_id,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
        ]);

        return true;
    }

    public function mergeGuestIntoCustomer(string $guestToken, Customer $customer): void
    {
        $guestWishlist = Wishlist::query()->where('guest_token', $guestToken)->where('customer_id', null)->first();

        if (! $guestWishlist) {
            return;
        }

        $customerWishlist = $this->getOrCreateWishlist($customer, null);
        $existingProductIds = $customerWishlist->items()->pluck('product_id')->all();

        foreach ($guestWishlist->items as $item) {
            if (! in_array($item->product_id, $existingProductIds, true)) {
                $customerWishlist->items()->create([
                    'tenant_id' => $customerWishlist->tenant_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                ]);
            }
        }

        $guestWishlist->delete();
    }
}