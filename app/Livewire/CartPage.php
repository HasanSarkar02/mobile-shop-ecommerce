<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\CartItem;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('storefront.layout')]
class CartPage extends Component
{
    public function updateQuantity(int $itemId, int $quantity, CartService $carts): void
    {
        $item = $this->authorizedItem($itemId);
        $carts->updateQuantity($item, $quantity);
        $this->dispatch('cart-updated');
    }

    public function removeItem(int $itemId, CartService $carts): void
    {
        $item = $this->authorizedItem($itemId);
        $carts->removeItem($item);
        $this->dispatch('cart-updated');
    }

    private function authorizedItem(int $itemId): CartItem
    {
        $item = CartItem::query()->with('cart')->findOrFail($itemId);
        $cart = app(CartService::class)->getOrCreateCart(Auth::guard('customer')->user(), request()->cookie('cart_token'));

        abort_unless($item->cart_id === $cart->id, 404);

        return $item;
    }

    public string $couponCode = '';

    public ?string $couponError = null;

    public function applyCoupon(CartService $carts, CouponService $coupons): void
    {
        $cart = $carts->getOrCreateCart(Auth::guard('customer')->user(), request()->cookie('cart_token'));
        $result = $coupons->applyToCart($cart, $this->couponCode, Auth::guard('customer')->user());

        $this->couponError = $result->valid ? null : $result->message;
        $this->couponCode = $result->valid ? '' : $this->couponCode;
    }

    public function removeCoupon(CartService $carts, CouponService $coupons): void
    {
        $cart = $carts->getOrCreateCart(Auth::guard('customer')->user(), request()->cookie('cart_token'));
        $coupons->removeFromCart($cart);
    }

    public function render(CartService $carts, CouponService $coupons)
    {
        $cart = $carts->getOrCreateCart(Auth::guard('customer')->user(), request()->cookie('cart_token'));
        $cart->load('items.variant.product.translations', 'items.variant.media', 'coupon');

        $subtotal = $cart->items->sum(fn ($item) => $item->lineTotal());
        $couponResult = $coupons->computeForCart($cart, Auth::guard('customer')->user());

        return view('livewire.cart-page', [
            'items' => $cart->items,
            'subtotal' => $subtotal,
            'coupon' => $cart->coupon,
            'discount' => $couponResult->valid ? $couponResult->discountAmount : 0,
        ]);
    }
}
