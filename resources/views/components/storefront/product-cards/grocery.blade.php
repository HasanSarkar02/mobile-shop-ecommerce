@php
    $variant = $card['variant'] ?? null;
    $outOfStock = (bool) ($variant['is_out_of_stock'] ?? false);
    $discount = $card['discount_percentage'] ?? null;
    $rating = $card['average_rating'];
    $reviews = $card['reviews_count'] ?? 0;
    $product = $card['product'] ?? null;
    $sellByUnit = $product?->sell_by_unit ?? '1.000';
    $sellByUnitFloat = is_numeric($sellByUnit) ? (float) $sellByUnit : 1.0;
    $sellByUnitFloat = $sellByUnitFloat > 0 ? $sellByUnitFloat : 1.0;
    $uomName = $product?->uom?->name ?? $product?->uom?->code ?? 'pcs';
    $variantId = $card['cta']['variant_id'] ?? $variant['id'] ?? null;
    $isPurchasable = $variant['purchasable'] ?? false;
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
     x-data="{ variantId: {{ $variantId ?? 'null' }}, quantity: {{ $sellByUnitFloat }} }">

    {{-- 1. WISHLIST BUTTON (ABSOLUTELY POSITIONED, HIGHEST Z-INDEX, OUTSIDE ANCHOR) --}}
    <div class="absolute right-2 top-2 z-30">
        <x-storefront.wishlist-button :id="$card['id']" :wishlisted="$card['wishlisted']" />
    </div>

    {{-- 2. THE LINK (STRICTLY WRAPS ONLY IMAGE, TITLE, AND PRICE) --}}
    <a href="{{ $card['url'] }}" class="flex flex-1 flex-col z-10 focus:outline-none">
        <div class="relative">
             <x-storefront.product-image :src="$card['has_image'] ? $card['image'] : null" :alt="$card['image_alt']" :dimmed="$outOfStock" :gallery="$card['gallery_images'] ?? []" :hover-enabled="$card['hover_gallery_enabled'] ?? false">
                <div class="pointer-events-none absolute left-1.5 top-1.5 flex flex-col items-start gap-0.5">
                    <x-storefront.discount-badge :percentage="$discount" />
                    @if ($variant['is_preorder'] ?? false)
                        <span class="rounded bg-amber-500 px-1 py-0.5 text-[9px] font-bold leading-none tracking-wide text-white shadow-sm">PRE-ORDER</span>
                    @endif
                </div>
                <x-storefront.stock-badge :show="$outOfStock" />
             </x-storefront.product-image>
        </div>
        <div class="flex flex-1 flex-col p-3">
             <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 leading-tight line-clamp-2 min-h-[2rem]">{{ $card['name'] }}</h3>
             <p class="text-xs text-gray-500 mt-0.5">{{ rtrim(rtrim(number_format($sellByUnitFloat, 3, '.', ''), '0'), '.') }} {{ $uomName }}</p>
             <x-storefront.product-rating :rating="$rating" :count="$reviews" />
             <div class="mt-1 flex items-baseline gap-1.5">
                 @if ($variant)
                     <span class="text-lg font-bold text-red-600">{{ money((int) $variant['price']) }}</span>
                     @if ($variant['compare_at_price'] && $variant['compare_at_price'] > $variant['price'])
                         <span class="text-xs line-through text-gray-400">{{ money((int) $variant['compare_at_price']) }}</span>
                     @endif
                 @else
                     <span class="text-sm text-gray-400 dark:text-gray-500">Price unavailable</span>
                 @endif
             </div>
        </div>
    </a>

    {{-- 3. THE CART ACTION (BOTTOM, ISOLATED, DIRECT STORE DISPATCH) --}}
    <div class="p-3 pt-0 z-20">
        @if ($variantId && $isPurchasable)
            <button type="button"
                @click.prevent="$store.cart.add(variantId, quantity)"
                :disabled="$store.cart.pending[variantId]"
                class="flex w-full items-center justify-center gap-2 rounded-md bg-[#16a34a] px-3 py-1.5 text-[12px] sm:text-xs font-semibold text-white transition hover:bg-green-700 disabled:opacity-60 whitespace-nowrap">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span x-show="!$store.cart.pending[variantId]">Add to Cart</span>
                <span x-show="$store.cart.pending[variantId]" x-cloak>Adding...</span>
            </button>
        @else
            <button type="button" disabled
                class="flex h-9 w-full cursor-not-allowed items-center justify-center rounded-lg bg-gray-100 px-3 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                Out of Stock
            </button>
        @endif
    </div>
</div>
