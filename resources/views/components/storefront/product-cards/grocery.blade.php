@php
    $variant = $card['variant'] ?? null;
    $cta = $card['cta'] ?? null;
    $outOfStock = (bool) ($variant['is_out_of_stock'] ?? false);
    $discount = $card['discount_percentage'] ?? null;
    $rating = $card['average_rating'];
    $reviews = $card['reviews_count'] ?? 0;
    $product = $card['product'] ?? null;
    $sellByUnit = $product?->sell_by_unit ?? '1.000';
    $sellByUnitFloat = is_numeric($sellByUnit) ? (float) $sellByUnit : 1.0;
    $sellByUnitFloat = $sellByUnitFloat > 0 ? $sellByUnitFloat : 1.0;
    $uomName = $product?->uom?->name ?? $product?->uom?->code ?? 'pcs';
    $variantId = $cta['variant_id'] ?? $variant['id'] ?? null;
    $isPurchasable = $variant['purchasable'] ?? false;
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition-all duration-200 hover:shadow-md hover:shadow-gray-900/5 dark:border-gray-800 dark:bg-gray-900">

    <a href="{{ $card['url'] }}"
        class="flex flex-1 flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 rounded-xl">
        <x-storefront.product-image :src="$card['has_image'] ? $card['image'] : null" :alt="$card['image_alt']" :dimmed="$outOfStock" :gallery="$card['gallery_images'] ?? []" :hover-enabled="$card['hover_gallery_enabled'] ?? false">
            <div class="pointer-events-none absolute left-1.5 top-1.5 flex flex-col items-start gap-0.5">
                <x-storefront.discount-badge :percentage="$discount" />
                @if ($variant['is_preorder'] ?? false)
                    <span class="rounded bg-amber-500 px-1 py-0.5 text-[9px] font-bold leading-none tracking-wide text-white shadow-sm">PRE-ORDER</span>
                @endif
            </div>
            <x-storefront.stock-badge :show="$outOfStock" />
        </x-storefront.product-image>

        <div class="flex flex-1 flex-col p-2">
            <h3 class="line-clamp-2 min-h-[2rem] text-xs font-medium leading-snug text-gray-900 transition group-hover:text-green-600 dark:text-gray-100">
                {{ $card['name'] }}
            </h3>

            @if ($product && $product->sell_by_unit)
                <p class="mt-1 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                    {{ rtrim(rtrim(number_format((float) $product->sell_by_unit, 3, '.', ''), '0'), '.') }} {{ $uomName }}
                </p>
            @endif

            <x-storefront.product-rating :rating="$rating" :count="$reviews" />

            <div class="mt-1">
                @if ($variant)
                    <x-ui.price size="sm" :price="$variant['price']" :compare-at-price="$variant['compare_at_price']" />
                @else
                    <span class="text-xs text-gray-400 dark:text-gray-500">Price unavailable</span>
                @endif
            </div>
        </div>
    </a>

    <x-storefront.wishlist-button :id="$card['id']" :wishlisted="$card['wishlisted']" />

    <div class="px-2 pb-2" x-data="{ quantity: {{ $sellByUnitFloat }}, step: {{ $sellByUnitFloat }}, variantId: {{ $variantId ?? 'null' }} }">
        @if ($variantId && $isPurchasable)
            <div class="flex items-center gap-1">
                <div class="flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <button type="button" @click="quantity = Math.max(step, parseFloat((quantity - step).toFixed(3)))"
                        class="w-7 h-7 flex items-center justify-center text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-green-600 transition"
                        aria-label="Decrease quantity">−</button>
                    <span class="w-8 text-center text-xs font-medium tabular-nums" x-text="Number.isInteger(quantity) ? quantity : quantity.toFixed(3).replace(/\.?0+$/, '')"></span>
                    <button type="button" @click="quantity = parseFloat((quantity + step).toFixed(3))"
                        class="w-7 h-7 flex items-center justify-center text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-green-600 transition"
                        aria-label="Increase quantity">+</button>
                </div>
                <button type="button" @click="$store.cart.add(variantId, quantity)"
                    :disabled="$store.cart.pending[variantId]"
                    class="flex flex-1 h-7 items-center justify-center gap-1 rounded-lg bg-green-600 px-2 text-xs font-semibold text-white transition hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 disabled:opacity-60">
                    <span x-show="!$store.cart.pending[variantId]">Add</span>
                    <span x-show="$store.cart.pending[variantId]" x-cloak class="inline-flex items-center gap-1">
                        <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    </span>
                </button>
            </div>
        @elseif ($variantId && ! $isPurchasable)
            <button type="button" disabled
                class="flex h-7 w-full cursor-not-allowed items-center justify-center rounded-lg bg-gray-100 px-2 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                Out of Stock
            </button>
        @else
            <div class="h-7" aria-hidden="true"></div>
        @endif
    </div>
</div>
