<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function store(Request $request, CartService $carts): RedirectResponse
    {
        try {
            $this->addToCart($request, $carts);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Added to cart.');
    }

    public function buyNow(Request $request, CartService $carts): RedirectResponse
    {
        try {
            $this->addToCart($request, $carts);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('storefront.checkout');
    }

    /**
     * Single shared purchase path for both Add to Cart and Buy Now. Validation
     * and stock/purchasability rules live in CartService::addItem →
     * InventoryService; nothing here re-implements them, so the two CTAs can
     * never drift apart.
     */
    private function addToCart(Request $request, CartService $carts): void
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $variant = ProductVariant::query()->findOrFail($data['product_variant_id']);
        $cart = $carts->getOrCreateCart(auth('customer')->user(), $request->cookie('cart_token'));

        $carts->addItem($cart, $variant, (int) $data['quantity']);
    }

    public function show()
    {
        return view('storefront.cart.show');
    }
}
