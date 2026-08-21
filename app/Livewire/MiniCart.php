<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class MiniCart extends Component
{
    /**
     * 'dropdown' — desktop header: icon + badge + hover/click preview panel.
     * 'badge' — mobile bottom nav: icon + badge only, links straight to /cart.
     * Both share the same cart fetch/refresh logic below rather than existing
     * as two separate components.
     */
    public string $variant = 'dropdown';

    public function render(CartService $carts)
    {
        $cart = $carts->getOrCreateCart(Auth::guard('customer')->user(), request()->cookie('cart_token'));
        $cart->load('items.variant.product.translations');

        return view('livewire.mini-cart', [
            'itemCount' => $cart->items->sum('quantity'),
            'subtotal' => $cart->items->sum(fn ($item) => $item->lineTotal()),
            'items' => $cart->items,
        ]);
    }

    #[On('cart-updated')]
    public function refresh(): void
    {
        // render() re-runs automatically; method exists purely to register the listener.
    }
}
