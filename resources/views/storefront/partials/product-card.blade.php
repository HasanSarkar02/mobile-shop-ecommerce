
@php
    $variant = $card['variant'] ?? null;
    $cta = $card['cta'] ?? null;
    $outOfStock = (bool) ($variant['is_out_of_stock'] ?? false);
    $discount = $card['discount_percentage'] ?? null;
    $rating = $card['average_rating'];
    $reviews = $card['reviews_count'] ?? 0;
    $hasRating = $reviews > 0 && $rating !== null;
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-gray-900/5 dark:border-gray-800 dark:bg-gray-900"
    x-data="{ imgLoaded: false, imgError: false }">

    <a href="{{ $card['url'] }}"
        class="flex flex-1 flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 rounded-2xl">
        {{-- Image area --}}
        <div class="relative aspect-square overflow-hidden bg-gray-50 dark:bg-gray-800/60">
            @if ($card['has_image'])
                {{-- Skeleton shows until the image decodes; disappears the moment it loads or errors. --}}
                <div x-show="!imgLoaded && !imgError" x-cloak
                    class="absolute inset-0 animate-pulse bg-gray-100 dark:bg-gray-800"></div>

                <img src="{{ $card['image'] }}" alt="{{ $card['image_alt'] }}" width="400" height="400" loading="lazy"
                    decoding="async" x-init="$el.complete && $el.naturalWidth > 0 && (imgLoaded = true)" x-on:load="imgLoaded = true" x-on:error="imgError = true"
                    x-show="!imgError"
                    class="h-full w-full object-cover transition duration-300 {{ $outOfStock ? 'opacity-60 grayscale' : 'group-hover:scale-[1.04]' }}">

                {{-- Broken-image fallback (404 / corrupt file / CDN miss) --}}
                <div x-show="imgError" x-cloak
                    class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 bg-gray-50 text-gray-300 dark:bg-gray-800/60 dark:text-gray-600">
                    <x-ui.icon name="image" class="h-8 w-8" />
                    <span class="text-[11px] font-medium text-gray-400 dark:text-gray-500">Image unavailable</span>
                </div>
            @else
                {{-- No image at all — polished placeholder, never a blank block --}}
                <div
                    class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 bg-gray-50 text-gray-300 dark:bg-gray-800/60 dark:text-gray-600">
                    <x-ui.icon name="image" class="h-8 w-8" />
                    <span class="text-[11px] font-medium text-gray-400 dark:text-gray-500">No image</span>
                </div>
            @endif

            {{-- Badge stack: top-left, stacked vertically, never overlapping wishlist button (top-right) --}}
            <div class="pointer-events-none absolute left-2 top-2 flex flex-col items-start gap-1">
                @if ($discount)
                    <span
                        class="rounded-md bg-red-600 px-1.5 py-0.5 text-[10px] font-bold leading-none tracking-wide text-white shadow-sm">
                        -{{ $discount }}%
                    </span>
                @endif
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

            @if ($outOfStock)
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/5">
                    <span class="rounded-full bg-gray-900/90 px-3 py-1 text-xs font-semibold text-white">
                        Out of Stock
                    </span>
                </div>
            @endif
        </div>

        {{-- Text content — fixed-height regions so cards in the same row end at the same height
             regardless of which optional pieces (rating, EMI) a given card has. --}}
        <div class="flex flex-1 flex-col p-3">
            <h3
                class="line-clamp-2 min-h-[2.25rem] text-[13px] font-medium leading-snug text-gray-900 transition group-hover:text-[var(--brand)] dark:text-gray-100 sm:text-sm">
                {{ $card['name'] }}
            </h3>

            <div class="mt-1 flex min-h-[1.1rem] items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                @if ($hasRating)
                    <span class="text-amber-500" aria-hidden="true">★</span>
                    <span
                        class="font-medium text-gray-700 dark:text-gray-300">{{ number_format((float) $rating, 1) }}</span>
                    <span>({{ $reviews }})</span>
                @endif
            </div>

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

    {{-- Wishlist button — overlays the image via the outer `relative` container.
         Sibling of the <a>, so it's its own focusable/clickable control. --}}
    <button type="button" x-init="$store.wishlist.seed({{ $card['id'] }}, {{ $card['wishlisted'] ? 'true' : 'false' }})" @click="$store.wishlist.toggle({{ $card['id'] }})"
        :disabled="$store.wishlist.pending[{{ $card['id'] }}]"
        :aria-busy="$store.wishlist.pending[{{ $card['id'] }}] ? 'true' : 'false'"
        :aria-pressed="$store.wishlist.isWishlisted({{ $card['id'] }}) ? 'true' : 'false'"
        :aria-label="$store.wishlist.isWishlisted({{ $card['id'] }}) ? 'Remove from wishlist' : 'Add to wishlist'"
        class="absolute right-2 top-2 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-gray-600 shadow-sm ring-1 ring-black/5 transition duration-150 hover:text-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:scale-90 disabled:opacity-70 dark:bg-gray-900/90 dark:text-gray-300 dark:ring-white/10">
        <span x-show="!$store.wishlist.isWishlisted({{ $card['id'] }})">
            <x-ui.icon name="heart" class="h-[18px] w-[18px]" />
        </span>
        <span x-show="$store.wishlist.isWishlisted({{ $card['id'] }})" x-cloak>
            <x-ui.icon name="heart-solid" :solid="true" class="h-[18px] w-[18px] text-red-500" />
        </span>
    </button>

    {{-- CTA — always reserves the same vertical slot so card heights never jump between states. --}}
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
                    Adding…
                </span>
            </button>
        @elseif ($cta && $cta['type'] === 'select_options')
            <a href="{{ $cta['url'] }}"
                class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-gray-300 px-3 text-xs font-semibold text-gray-800 transition hover:border-[var(--brand)] hover:text-[var(--brand)] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--brand)] dark:border-gray-700 dark:text-gray-100 dark:focus-visible:ring-offset-gray-900 sm:text-sm">
                Select Options
            </a>
        @elseif ($cta && $cta['type'] === 'disabled')
            <button type="button" disabled
                class="flex h-9 w-full cursor-not-allowed items-center justify-center rounded-xl bg-gray-100 px-3 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500 sm:text-sm">
                {{ $cta['label'] }}
            </button>
        @else
            {{-- No purchasable state at all (discontinued / no variants) — reserve the
                 slot silently so grid rows still align; no fake control is rendered. --}}
            <div class="h-9" aria-hidden="true"></div>
        @endif
    </div>
</div>
