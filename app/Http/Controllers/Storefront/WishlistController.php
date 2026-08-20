<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Services\Storefront\ProductCardData;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request, WishlistService $wishlists, ProductCardData $cards)
    {
        $wishlist = $wishlists->getOrCreateWishlist(auth('customer')->user(), $request->cookie('wishlist_token'));
        $wishlist->load('items.product.translations', 'items.product.variants', 'items.product.media', 'items.product.emiPlans');

        $products = $wishlist->items->map(fn (WishlistItem $item) => $item->product);
        $wishlistedIds = $wishlist->items->pluck('product_id');

        return view('storefront.wishlist.index', [
            'wishlist' => $wishlist,
            'cards' => $cards->forMany($products, $wishlistedIds),
        ]);
    }

    /**
     * Toggles a product in/out of the visitor's wishlist. AJAX/JSON callers
     * (the shared Alpine wishlist store) get the resolved state back so the
     * client can reconcile optimistic updates; non-JSON callers keep the
     * redirect + flash behaviour.
     */
    public function toggle(Request $request, WishlistService $wishlists): JsonResponse|RedirectResponse
    {
        $wishlist = $wishlists->getOrCreateWishlist(auth('customer')->user(), $request->cookie('wishlist_token'));
        $product = Product::query()->findOrFail($request->input('product_id'));

        $added = $wishlists->toggle($wishlist, $product, $request->input('variant_id'));

        if ($request->wantsJson()) {
            return response()->json(['wishlisted' => $added]);
        }

        return back()->with('status', $added ? 'Added to wishlist.' : 'Removed from wishlist.');
    }
}
