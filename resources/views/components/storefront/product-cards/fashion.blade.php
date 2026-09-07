@php
    $variant = $card['variant'] ?? null;
    $cta = $card['cta'] ?? null;
    $outOfStock = (bool) ($variant['is_out_of_stock'] ?? false);
    $discount = $card['discount_percentage'] ?? null;
    $rating = $card['average_rating'];
    $reviews = $card['reviews_count'] ?? 0;
    $gallery = $card['gallery_images'] ?? [];
    $hasGallery = count($gallery) > 1;
    $totalImages = count($gallery);
    $primary = $card['has_image'] ? $card['image'] : null;
    $alt = $card['image_alt'] ?? ($card['name'] ?? '');
    // Fashion uses portrait aspect
    $aspect = \App\Support\IndustryConfig::currentGet('ui.image_aspect', 'aspect-[3/4]');
    // Badge selection (fashion premium - single compact badge top-left)
    $badge = null;
    $badgeClasses = '';
    if ($discount !== null && $discount > 0) {
        $badge = $discount . '% OFF';
        $badgeClasses = 'bg-[#ec4899] text-white';
    } elseif ($outOfStock) {
        $badge = null; // stock overlay handles
    } else {
        $productModel = $card['product'] ?? null;
        $viewCount = $productModel ? (int) ($productModel->view_count ?? 0) : 0;
        $isFeatured = $productModel ? (bool) ($productModel->is_featured ?? false) : false;
        $createdAt = $productModel ? $productModel->created_at : null;
        $isNew = $createdAt && $createdAt->diffInDays(now()) <= 14;
        // low-stock check via variant stock_status
        $stockStatus = $variant['stock_status'] ?? null;
        $isLowStock = $stockStatus === 'low_stock';
        if ($isLowStock) {
            $badge = 'LOW STOCK';
            $badgeClasses = 'bg-orange-500 text-white';
        } elseif ($isNew) {
            $badge = 'NEW';
            $badgeClasses = 'bg-emerald-600 text-white';
        } elseif ($isFeatured) {
            $badge = 'BESTSELLER';
            $badgeClasses = 'bg-gray-900 text-white';
        } elseif ($viewCount > 100) {
            $badge = 'TRENDING';
            $badgeClasses = 'bg-gray-900 text-white';
        }
    }
    $swatches = $card['swatches'] ?? [];
    $swatchesOverflow = $card['swatches_overflow'] ?? 0;
    $hoverEnabled = $card['hover_gallery_enabled'] ?? false;
@endphp

<div
    class="group relative flex flex-col overflow-hidden rounded-xl bg-white border border-gray-100 transition duration-200 hover:shadow-md hover:shadow-gray-900/[0.06] dark:border-gray-800 dark:bg-gray-900">

    {{-- Image area: portrait, dominates card --}}
    <div x-data="{
        activeIndex: 0,
        total: @js($totalImages),
        hasGallery: @js($hasGallery),
        hoverEnabled: @js($hoverEnabled),
        touchStartX: 0,
        touchStartY: 0,
        touchDeltaX: 0,
        dragging: false,
        canHover() { return window.matchMedia('(hover: hover) and (pointer: fine)').matches; },
        handleEnter() {
            if (!this.hasGallery || !this.hoverEnabled || !this.canHover()) return;
            if (this.total <= 1) return;
            this.activeIndex = 1;
        },
        handleLeave() {
            if (!this.canHover()) return;
            this.activeIndex = 0;
        },
        onTouchStart(e) {
            if (!this.hasGallery) return;
            this.touchStartX = e.touches[0].clientX;
            this.touchStartY = e.touches[0].clientY;
            this.dragging = true;
            this.touchDeltaX = 0;
        },
        onTouchMove(e) {
            if (!this.dragging || !this.hasGallery) return;
            const dx = e.touches[0].clientX - this.touchStartX;
            const dy = e.touches[0].clientY - this.touchStartY;
            if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 10) {
                this.dragging = false;
                return;
            }
            this.touchDeltaX = dx;
        },
        onTouchEnd(e) {
            if (!this.dragging || !this.hasGallery) return;
            this.dragging = false;
            const threshold = 40;
            if (this.touchDeltaX < -threshold && this.activeIndex < this.total - 1) {
                this.activeIndex++;
            } else if (this.touchDeltaX > threshold && this.activeIndex > 0) {
                this.activeIndex--;
            }
            this.touchDeltaX = 0;
        },
        goTo(idx) { if (idx >= 0 && idx < this.total) this.activeIndex = idx; }
    }" @mouseenter="handleEnter()" @mouseleave="handleLeave()"
        @touchstart.passive="onTouchStart($event)" @touchmove.passive="onTouchMove($event)" @touchend="onTouchEnd($event)"
        class="relative {{ $aspect }} overflow-hidden bg-[#f8f5f0] dark:bg-gray-800/40 select-none touch-pan-y">
        <a href="{{ $card['url'] }}"
            class="absolute inset-0 z-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-inset"
            aria-label="{{ $card['name'] }}">
            @if ($primary)
                {{-- Layered images with opacity crossfade 220ms ease-out --}}
                @foreach ($gallery as $idx => $g)
                    <img src="{{ $g['src'] }}" alt="{{ $g['alt'] }}" width="400" height="533"
                        loading="lazy" decoding="async"
                        @if ($idx === 0) x-init="$el.complete && $el.naturalWidth > 0" @endif
                        :class="activeIndex === {{ $idx }} ? 'opacity-100' : 'opacity-0'"
                        class="absolute inset-0 h-full w-full object-cover object-center transition-opacity duration-[240ms] ease-out {{ $outOfStock ? 'opacity-60 grayscale' : '' }}"
                        draggable="false">
                @endforeach
                @if (empty($gallery))
                    <img src="{{ $primary }}" alt="{{ $alt }}" width="400" height="533"
                        loading="lazy" decoding="async"
                        class="absolute inset-0 h-full w-full object-cover object-center {{ $outOfStock ? 'opacity-60 grayscale' : '' }}">
                @endif
            @else
                <div
                    class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 bg-[#f8f5f0] text-gray-400 dark:bg-gray-800/40">
                    <x-ui.icon name="image" class="h-8 w-8" />
                    <span class="text-[11px] font-medium">{{ __('No image') }}</span>
                </div>
            @endif
        </a>

        {{-- Badge top-left --}}
        @if ($badge)
            <div class="pointer-events-none absolute left-2 top-2 z-[2]">
                <span
                    class="inline-flex items-center rounded-[6px] px-2 py-1 text-[10px] font-bold leading-none tracking-wide shadow-sm {{ $badgeClasses }}">
                    {{ $badge }}
                </span>
            </div>
        @elseif ($discount)
        @endif

        {{-- PRE-ORDER overlay badge if not already shown as % OFF --}}
        @if (($variant['is_preorder'] ?? false) && $badge === null)
            <div class="pointer-events-none absolute left-2 top-2 z-[2]">
                <span
                    class="inline-flex rounded-[6px] bg-amber-500 px-2 py-1 text-[10px] font-bold leading-none tracking-wide text-white shadow-sm">{{ __('PRE-ORDER') }}</span>
            </div>
        @endif

        {{-- Wishlist top-right (direct, no wrapper offset) --}}
        <x-storefront.wishlist-button :id="$card['id']" :wishlisted="$card['wishlisted']" />

        {{-- Quick view eye bottom-center - desktop hover only (deferred infrastructure, links to PDP) --}}
        <a href="{{ $card['url'] }}" aria-label="Quick view {{ $card['name'] }}"
            class="absolute bottom-10 left-1/2 z-[2] hidden h-8 -translate-x-1/2 items-center justify-center rounded-full bg-white/95 px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-black/5 backdrop-blur transition duration-200 hover:bg-white hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] md:flex opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75"
                stroke="currentColor" class="h-3.5 w-3.5 mr-1" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Quick view
        </a>

        {{-- Gallery dots bottom-center --}}
        @if ($hasGallery)
            <div
                class="pointer-events-auto absolute bottom-2 left-1/2 z-[2] flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-black/20 px-2 py-1.5 backdrop-blur-sm md:bg-black/15">
                @foreach ($gallery as $idx => $g)
                    <button type="button" @click.prevent.stop="goTo({{ $idx }})"
                        :aria-label="'View image ' + ({{ $idx }} + 1)"
                        :aria-current="activeIndex === {{ $idx }} ? 'true' : 'false'"
                        class="h-1.5 w-1.5 rounded-full transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-1 focus-visible:ring-offset-black/20"
                        :class="activeIndex === {{ $idx }} ? 'bg-white w-3' : 'bg-white/60 hover:bg-white/80'">
                    </button>
                @endforeach
            </div>

            {{-- Mobile counter bottom-right (visible only on small screens) --}}
            <div class="pointer-events-none absolute bottom-2 right-2 z-[2] flex md:hidden items-center rounded-full bg-black/55 px-2 py-1 text-[10px] font-medium leading-none text-white backdrop-blur-sm"
                x-show="hasGallery" x-cloak>
                <span x-text="(activeIndex + 1) + ' / ' + total"></span>
            </div>
        @endif

        {{-- Out of stock overlay --}}
        <x-storefront.stock-badge :show="$outOfStock" />
    </div>

    {{-- Product information - clean, compact, editorial --}}
    <div class="flex flex-1 flex-col p-3 pt-3 gap-1">
        <a href="{{ $card['url'] }}"
            class="focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 rounded-sm">
            <h3
                class="line-clamp-2 min-h-[2.45rem] text-[13px] font-medium leading-snug text-gray-900 dark:text-gray-100">
                {{ $card['name'] }}
            </h3>
        </a>

        {{-- Rating subtle --}}
        @if (($reviews ?? 0) > 0 && $rating !== null)
            <div class="flex items-center gap-1 text-xs">
                <span class="text-amber-500" aria-hidden="true">★</span>
                <span
                    class="font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) $rating, 1) }}</span>
                <span class="text-gray-500 dark:text-gray-400">({{ $reviews }})</span>
            </div>
        @else
            <div class="min-h-[1rem]"></div>
        @endif

        {{-- Price row - current strongest --}}
        <div class="flex items-baseline gap-2 flex-wrap">
            @if ($variant)
                <span
                    class="text-[15px] font-bold leading-none text-gray-900 dark:text-gray-50">{{ money_without_trailing_zeros((int) $variant['price']) }}</span>
                @if ($variant['compare_at_price'] && $variant['compare_at_price'] > $variant['price'])
                    <span
                        class="text-xs font-normal text-gray-400 line-through">{{ money_without_trailing_zeros((int) $variant['compare_at_price']) }}</span>
                    @if ($discount)
                        <span class="text-xs font-bold text-[#e11d48]">-{{ $discount }}%</span>
                    @endif
                @endif
            @else
                <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Price unavailable') }}</span>
            @endif
        </div>

        {{-- Swatches + CTA bottom row (44px CTA) --}}
        <div class="mt-1 flex items-center justify-between gap-2 min-h-[44px]">
            <div class="flex items-center gap-1.5">
                @if (!empty($swatches))
                    @foreach ($swatches as $swatch)
                        <span
                            class="inline-block h-[18px] w-[18px] rounded-full ring-1 ring-black/10 dark:ring-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-1"
                            style="background-color: {{ $swatch['hex'] }}; {{ strtolower($swatch['value']) === 'white' ? 'border:1px solid #e5e7eb;' : '' }}"
                            title="{{ $swatch['name'] }}" aria-label="Color {{ $swatch['name'] }}"
                            role="img"></span>
                    @endforeach
                    @if ($swatchesOverflow > 0)
                        <span
                            class="text-xs font-medium text-gray-600 dark:text-gray-400">+{{ $swatchesOverflow }}</span>
                    @endif
                @else
                    <span class="text-xs text-transparent" aria-hidden="true">.</span>
                @endif
            </div>

            {{-- Compact CTA - increased to 44px, uses store primary color --}}
            <div class="shrink-0">
                @if ($cta && $cta['type'] === 'add_to_cart')
                    <div x-data="{ variantId: {{ $cta['variant_id'] !== null ? $cta['variant_id'] : 'null' }} }" x-init="if (variantId && $store.cart) $store.cart.pending[variantId] = false">
                        <button type="button"
                            @click.prevent="
                                if (!variantId) {
                                    $store.cart.toast('Variant not available', 'error');
                                    return;
                                }
                                $store.cart.add(variantId, 1, '{{ route('storefront.cart.store') }}')
                            "
                            :disabled="variantId && $store.cart.pending[variantId]"
                            :aria-busy="variantId && $store.cart.pending[variantId] ? 'true' : 'false'"
                            aria-label="{{ $cta['label'] }} {{ $card['name'] }}"
                            class="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl bg-[var(--brand)] text-white shadow-sm transition hover:brightness-110 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="!(variantId && $store.cart.pending[variantId])"
                                class="flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.9" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 9.465A2.25 2.25 0 0119.245 21H4.755a2.25 2.25 0 01-2.243-2.532l1.263-9.465A1.5 1.5 0 014.26 7.5h15.48a1.5 1.5 0 011.486 1.507z" />
                                </svg>
                            </span>
                            <span x-show="variantId && $store.cart.pending[variantId]" x-cloak
                                class="flex items-center justify-center">
                                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"
                                    aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                @elseif ($cta && $cta['type'] === 'select_options')
                    <x-storefront.variant-modal :card="$card">
                        <x-slot:trigger>
                            <button type="button" @click="open = true"
                                aria-label="{{ __('Add to Cart') }} {{ $card['name'] ?? '' }}"
                                class="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl bg-[var(--brand)] text-white shadow-sm transition hover:brightness-110 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.9" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 9.465A2.25 2.25 0 0119.245 21H4.755a2.25 2.25 0 01-2.243-2.532l1.263-9.465A1.5 1.5 0 014.26 7.5h15.48a1.5 1.5 0 011.486 1.507z" />
                                </svg>
                            </button>
                        </x-slot:trigger>
                    </x-storefront.variant-modal>
                @elseif ($cta && $cta['type'] === 'disabled')
                    <button type="button" disabled aria-label="{{ __('Out of stock') }}"
                        class="inline-flex h-11 w-11 cursor-not-allowed items-center justify-center rounded-xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.75" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 9.465A2.25 2.25 0 0119.245 21H4.755a2.25 2.25 0 01-2.243-2.532l1.263-9.465A1.5 1.5 0 014.26 7.5h15.48a1.5 1.5 0 011.486 1.507z" />
                        </svg>
                    </button>
                @else
                    <div class="h-11 w-11" aria-hidden="true"></div>
                @endif
            </div>
        </div>
    </div>
</div>
