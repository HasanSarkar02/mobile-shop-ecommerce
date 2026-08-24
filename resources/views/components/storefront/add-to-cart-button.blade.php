@props(['type' => 'cart'])

{{-- Shared add-to-cart / buy-now primitive (F.4). Extracted verbatim from
     resources/views/storefront/products/show.blade.php (main buy box and the
     sticky mobile bar both used these exact blocks).
     type="buy-now": the server POST form with client-side purchasable guard.
     type="cart":    the secondary Alpine button calling addToCart().
     Reads the surrounding productDetail(...) Alpine scope: current(),
     currentVariantId, quantity, pending, cartLoading, addToCart(). --}}
@if ($type === 'buy-now')
    <form method="POST" action="{{ route('storefront.buy-now') }}" class="flex-1"
        x-data="{ pending: false }"
        @submit="if (!current() || !current().purchasable) { $event.preventDefault(); return; } pending = true">
        @csrf
        <input type="hidden" name="product_variant_id" :value="currentVariantId || ''">
        <input type="hidden" name="quantity" :value="quantity">
        <x-ui.button variant="primary" size="lg" class="w-full" type="submit"
            x-bind:disabled="pending || cartLoading || !current() || !current().purchasable">
            <span x-text="current() && current().purchase_state === 'preorder' ? 'Pre-Order Now' : 'Buy Now'"></span>
        </x-ui.button>
    </form>
@else
    <x-ui.button variant="secondary" size="lg" class="w-full" @click="addToCart()"
        x-bind:disabled="cartLoading || !current() || !current().purchasable">
        <span x-text="ctaLabel()"></span>
    </x-ui.button>
@endif
