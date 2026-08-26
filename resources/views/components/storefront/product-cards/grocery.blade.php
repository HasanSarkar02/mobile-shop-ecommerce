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

<div class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition-all duration-200 hover:shadow-md hover:shadow-gray-900/5 dark:border-gray-800 dark:bg-gray-900">
    <div class="relative">
        <a href="{{ $card['url'] }}" class="block rounded-t-xl">
            <x-storefront.product-image :src="$card['has_image'] ? $card['image'] : null" :alt="$card['image_alt']" :dimmed="$outOfStock" :gallery="$card['gallery_images'] ?? []" :hover-enabled="$card['hover_gallery_enabled'] ?? false">
                <div class="pointer-events-none absolute left-1.5 top-1.5 flex flex-col items-start gap-0.5">
                    <x-storefront.discount-badge :percentage="$discount" />
                    @if ($variant['is_preorder'] ?? false)
                        <span class="rounded bg-amber-500 px-1 py-0.5 text-[9px] font-bold leading-none tracking-wide text-white shadow-sm">PRE-ORDER</span>
                    @endif
                </div>
                <x-storefront.stock-badge :show="$outOfStock" />
            </x-storefront.product-image>
        </a>
        <div class="absolute top-2 right-2 z-20 cursor-pointer pointer-events-auto" @click.prevent>
            <x-storefront.wishlist-button :id="$card['id']" :wishlisted="$card['wishlisted']" />
        </div>
    </div>

    <div class="flex flex-1 flex-col p-3">
        <a href="{{ $card['url'] }}" class="block rounded">
            <h3 class="line-clamp-2 min-h-[2rem] text-sm font-medium leading-snug text-gray-900 transition group-hover:text-green-600 dark:text-gray-100">
                {{ $card['name'] }}
            </h3>
        </a>

        @if ($product && $product->sell_by_unit)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ rtrim(rtrim(number_format((float) $product->sell_by_unit, 3, '.', ''), '0'), '.') }} {{ $uomName }}
            </p>
        @endif

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

    <div class="px-3 pb-3" x-data="{ variantId: {{ $variantId ?? 'null' }}, quantity: {{ $sellByUnitFloat }} }">
        @if ($variantId && $isPurchasable)
            <button type="button" @click.prevent="$store.cart.add(variantId, quantity)"
                :disabled="$store.cart.pending[variantId]"
                :aria-busy="$store.cart.pending[variantId] ? 'true' : 'false'"
                class="w-full bg-[#16a34a] hover:bg-green-700 text-white font-semibold rounded py-1.5 px-2 flex items-center justify-center gap-2 whitespace-nowrap text-[11px] sm:text-xs transition-colors focus:outline-none disabled:opacity-60 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4 flex-shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.885-4.752 2.244-7.313.075-.539-.373-.937-.917-.937H5.106M7.5 14.25L5.106 5.25M7.5 14.25L5.25 21m10.5-3.75a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM19.5 21a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                </svg>
                <span x-show="!$store.cart.pending[variantId]" class="whitespace-nowrap">Add to Cart</span>
                <span x-show="$store.cart.pending[variantId]" x-cloak class="inline-flex items-center gap-1.5 whitespace-nowrap">
                    <svg class="h-3 w-3 animate-spin flex-shrink-0" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    Adding...
                </span>
            </button>
        @elseif ($variantId && ! $isPurchasable)
            <button type="button" disabled
                class="flex h-9 w-full cursor-not-allowed items-center justify-center rounded-lg bg-gray-100 px-3 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                Out of Stock
            </button>
        @else
            <div class="h-9" aria-hidden="true"></div>
        @endif
    </div>
</div>
