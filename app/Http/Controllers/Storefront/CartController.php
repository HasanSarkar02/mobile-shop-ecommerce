<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /** Hard per-request sanity cap; stock-aware limits live in InventoryService. */
    private const MAX_QUANTITY = 99;

    public function store(Request $request, CartService $carts): RedirectResponse|JsonResponse
    {
        try {
            $this->addToCart($request, $carts);
        } catch (\RuntimeException $e) {
            // JSON callers must see the real failure — a redirect would be
            // silently followed by fetch and reported as a false success.
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        // Server-truth for hybrid optimistic reconciliation: always return decimal-aware cart_count
        // Use bcadd to sum decimal quantities correctly for sell_by_unit measured goods.
        $cartForCount = $carts->getOrCreateCart(auth('customer')->user(), $request->cookie('cart_token'));
        $cartForCount->load('items');
        $cartCount = '0.000';
        foreach ($cartForCount->items as $item) {
            $cartCount = bcadd($cartCount, (string) $item->quantity, 3);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Added to cart.', 'cart_count' => $cartCount]);
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
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:'.self::MAX_QUANTITY],
        ]);

        $variant = ProductVariant::query()->with('product')->findOrFail($data['product_variant_id']);
        $variant->loadMissing('product');

        $qty = number_format((float) $data['quantity'], 3, '.', '');

        // sell_by_unit multiple validation (Phase C-3)
        /** @var mixed $step */
        $step = $variant->product?->sell_by_unit;
        if (filled($step) && bccomp((string) $step, '0', 3) === 1) {
            $stepStr = number_format((float) $step, 3, '.', '');
            if (bccomp(bcmod($qty, $stepStr, 3), '0', 3) !== 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Quantity must be a multiple of {$stepStr}."],
                ]);
            }
        }

        $cart = $carts->getOrCreateCart(auth('customer')->user(), $request->cookie('cart_token'));

        $carts->addItem($cart, $variant, $qty);
    }

    public function show()
    {
        return view('storefront.cart.show');
    }
}
