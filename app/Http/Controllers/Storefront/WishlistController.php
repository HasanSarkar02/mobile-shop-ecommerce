<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request, WishlistService $wishlists)
    {
        $wishlist = $wishlists->getOrCreateWishlist(auth('customer')->user(), $request->cookie('wishlist_token'));
        $wishlist->load('items.product.translations', 'items.product.variants');

        return view('storefront.wishlist.index', compact('wishlist'));
    }

    public function toggle(Request $request, WishlistService $wishlists): RedirectResponse
    {
        $wishlist = $wishlists->getOrCreateWishlist(auth('customer')->user(), $request->cookie('wishlist_token'));
        $product = Product::query()->findOrFail($request->input('product_id'));

        $added = $wishlists->toggle($wishlist, $product, $request->input('variant_id'));

        return back()->with('status', $added ? 'Added to wishlist.' : 'Removed from wishlist.');
    }
}