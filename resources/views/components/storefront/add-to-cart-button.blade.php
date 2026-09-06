@props(['type' => 'cart'])

@if ($type === 'buy-now')
    <form method="POST" action="{{ route('storefront.buy-now') }}" class="flex-1" x-data="{ pending: false }"
        @submit="if (!current() || !current().purchasable) { $event.preventDefault(); return; } pending = true">
        @csrf
        <input type="hidden" name="product_variant_id" :value="currentVariantId || ''">
        <input type="hidden" name="quantity" :value="quantity">
        <x-ui.button variant="primary" size="lg" class="w-full" type="submit"
            x-bind:disabled="pending || cartLoading || !current() || !current().purchasable">
            <span x-text="current() && current().purchase_state === 'preorder' ? (i18n.preOrderNow || 'Pre-Order Now') : (i18n.buyNow || 'Buy Now')"></span>
        </x-ui.button>
    </form>
@else
    <x-ui.button variant="secondary" size="lg" class="w-full" @click="addToCart()"
        x-bind:disabled="cartLoading || !current() || !current().purchasable">
        <span x-text="ctaLabel()"></span>
    </x-ui.button>
@endif
