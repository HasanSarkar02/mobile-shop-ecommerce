<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Collection;

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

    /**
     * Resolve which of the given product ids are already in the visitor's
     * default wishlist. Deliberately queries an EXISTING wishlist and never
     * creates one just to answer a membership check, so listing pages have no
     * side effects. Returns an empty collection for anonymous visitors who
     * have never touched the wishlist feature.
     *
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, int>
     */
    public function wishlistedProductIds(Collection $productIds): Collection
    {
        if ($productIds->isEmpty()) {
            return collect();
        }

        $customer = auth('customer')->user();
        $guestToken = request()->cookie('wishlist_token');

        if (! $customer && ! $guestToken) {
            return collect();
        }

        return WishlistItem::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('wishlist', function ($query) use ($customer, $guestToken): void {
                $query->where('is_default', true);
                $customer ? $query->where('customer_id', $customer->id) : $query->where('guest_token', $guestToken);
            })
            ->pluck('product_id');
    }

    /**
     * Count of items in the visitor's default wishlist, without ever creating
     * a wishlist as a side effect. Used for the header/mobile count badge.
     */
    public function wishlistCount(): int
    {
        if ($customer = auth('customer')->user()) {
            return WishlistItem::query()
                ->whereHas('wishlist', fn ($query) => $query->where('customer_id', $customer->id)->where('is_default', true))
                ->count();
        }

        if ($token = request()->cookie('wishlist_token')) {
            return WishlistItem::query()
                ->whereHas('wishlist', fn ($query) => $query->where('guest_token', $token)->where('is_default', true))
                ->count();
        }

        return 0;
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
