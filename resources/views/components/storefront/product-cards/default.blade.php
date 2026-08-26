
@php
    $variant = $card['variant'] ?? null;
    $cta = $card['cta'] ?? null;
    $outOfStock = (bool) ($variant['is_out_of_stock'] ?? false);
    $discount = $card['discount_percentage'] ?? null;
    $rating = $card['average_rating'];
    $reviews = $card['reviews_count'] ?? 0;
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-gray-900/5 dark:border-gray-800 dark:bg-gray-900">

    <a href="{{ $card['url'] }}"
        class="flex flex-1 flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 rounded-2xl">
        {{-- Image area --}}
        <x-storefront.product-image :src="$card['has_image'] ? $card['image'] : null" :alt="$card['image_alt']" :dimmed="$outOfStock" :gallery="$card['gallery_images'] ?? []" :hover-enabled="$card['hover_gallery_enabled'] ?? false">
            {{-- Badge stack: top-left, stacked vertically, never overlapping wishlist button (top-right) --}}
            <div class="pointer-events-none absolute left-2 top-2 flex flex-col items-start gap-1">
                <x-storefront.discount-badge :percentage="$discount" />
                @if ($variant['is_preorder'] ?? false)
                    <span
                        class="rounded-md bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold leading-none tracking-wide text-white shadow-sm">
                        PRE-ORDER
                    </span>
                @endif
                @if ($card['is_official_import'])
                    <span
                        class="rounded-md bg-gray-900/85 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white shadow-sm dark:bg-white/90 dark:text-gray-900">
                        Official
                    </span>
                @endif
            </div>

            <x-storefront.stock-badge :show="$outOfStock" />
        </x-storefront.product-image>

        {{-- Text content â€” fixed-height regions so cards in the same row end at the same height
             regardless of which optional pieces (rating, EMI) a given card has. --}}
        <div class="flex flex-1 flex-col p-3">
            <h3
                class="line-clamp-2 min-h-[2.25rem] text-[13px] font-medium leading-snug text-gray-900 transition group-hover:text-[var(--brand)] dark:text-gray-100 sm:text-sm">
                {{ $card['name'] }}
            </h3>

            <x-storefront.product-rating :rating="$rating" :count="$reviews" />

            <div class="mt-1.5">
                @if ($variant)
                    <x-ui.price size="sm" :price="$variant['price']" :compare-at-price="$variant['compare_at_price']" />
                @else
                    <span class="text-sm text-gray-400 dark:text-gray-500">Price unavailable</span>
                @endif
            </div>

            <div class="mt-0.5 min-h-[1rem]">
                @if ($card['emi_available'])
                    <p class="text-[11px] font-medium text-[var(--brand)]">EMI available</p>
                @endif
            </div>
        </div>
    </a>

    {{-- Wishlist button â€” overlays the image via the outer `relative` container.
         Sibling of the <a>, so it's its own focusable/clickable control. --}}
    <x-storefront.wishlist-button :id="$card['id']" :wishlisted="$card['wishlisted']" />

    {{-- CTA â€” always reserves the same vertical slot so card heights never jump between states. --}}
    <div class="px-3 pb-3">
        @if ($cta && $cta['type'] === 'add_to_cart')
            <button type="button" @click="$store.cart.add({{ $cta['variant_id'] }})"
                :disabled="$store.cart.pending[{{ $cta['variant_id'] }}]"
                :aria-busy="$store.cart.pending[{{ $cta['variant_id'] }}] ? 'true' : 'false'"
                class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl bg-[var(--brand)] px-3 text-xs font-semibold text-white transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--brand)] disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-gray-900 sm:text-sm">
                <span x-show="!$store.cart.pending[{{ $cta['variant_id'] }}]">{{ $cta['label'] }}</span>
                <span x-show="$store.cart.pending[{{ $cta['variant_id'] }}]" x-cloak
                    class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Addingâ€¦
                </span>
            </button>
        @elseif ($cta && $cta['type'] === 'select_options')
            <x-storefront.variant-modal :card="$card" />
        @elseif ($cta && $cta['type'] === 'disabled')
            <button type="button" disabled
                class="flex h-9 w-full cursor-not-allowed items-center justify-center rounded-xl bg-gray-100 px-3 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500 sm:text-sm">
                {{ $cta['label'] }}
            </button>
        @else
            {{-- No purchasable state at all (discontinued / no variants) â€” reserve the
                 slot silently so grid rows still align; no fake control is rendered. --}}
            <div class="h-9" aria-hidden="true"></div>
        @endif
    </div>
</div>

