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
    $alt = $card['image_alt'] ?? $card['name'] ?? '';
    $brandName = $card['brand_name'] ?? null;
    $hoverEnabled = $card['hover_gallery_enabled'] ?? false;
@endphp

<div class="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition duration-200 hover:shadow-md hover:shadow-gray-900/[0.04] dark:border-gray-800 dark:bg-gray-900">

    {{-- Image area: square, premium — white background, minimized padding, scaled image for 70–80% product coverage --}}
    <div
        x-data="{
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
            goTo(idx) { if (idx >=0 && idx < this.total) this.activeIndex = idx; }
        }"
        @mouseenter="handleEnter()"
        @mouseleave="handleLeave()"
        @touchstart.passive="onTouchStart($event)"
        @touchmove.passive="onTouchMove($event)"
        @touchend="onTouchEnd($event)"
        class="relative aspect-square overflow-hidden bg-white dark:bg-gray-800/30 select-none touch-pan-y"
    >
        <a href="{{ $card['url'] }}" class="absolute inset-0 z-0 flex items-center justify-center p-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-inset" aria-label="{{ $card['name'] }}">
            @if ($primary && !empty($gallery))
                @foreach ($gallery as $idx => $g)
                    <img
                        src="{{ $g['src'] }}"
                        alt="{{ $g['alt'] }}"
                        width="300" height="300"
                        loading="lazy" decoding="async"
                        :class="activeIndex === {{ $idx }} ? 'opacity-100' : 'opacity-0'"
                        class="absolute inset-0 h-full w-full object-contain object-center p-2 scale-[1.18] transition-opacity duration-200 ease-out {{ $outOfStock ? 'opacity-40 grayscale' : '' }}"
                        draggable="false"
                    >
                @endforeach
            @elseif ($primary)
                <img src="{{ $primary }}" alt="{{ $alt }}" width="300" height="300" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-contain object-center p-2 scale-[1.18] {{ $outOfStock ? 'opacity-40 grayscale' : '' }}" draggable="false">
            @else
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-gray-400 dark:text-gray-500">
                    <x-ui.icon name="image" class="h-8 w-8" />
                    <span class="text-[11px] font-medium">{{ __('No image') }}</span>
                </div>
            @endif
        </a>

        {{-- Badge stack top-left --}}
        <div class="pointer-events-none absolute left-2 top-2 z-[2] flex flex-col items-start gap-1">
            <x-storefront.discount-badge :percentage="$discount" />
            @if (($variant['is_preorder'] ?? false) && $discount === null)
                <span class="rounded-md bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold leading-none tracking-wide text-white shadow-sm">{{ __('PRE-ORDER') }}</span>
            @endif
        </div>

        <x-storefront.stock-badge :show="$outOfStock" />

        <x-storefront.wishlist-button :id="$card['id']" :wishlisted="$card['wishlisted']" />

        {{-- Gallery dots bottom-center --}}
        @if ($hasGallery)
            <div class="pointer-events-auto absolute bottom-2 left-1/2 z-[2] flex -translate-x-1/2 items-center gap-1 rounded-full bg-black/10 px-2 py-1.5 backdrop-blur-sm dark:bg-white/10">
                @foreach ($gallery as $idx => $g)
                    <button type="button" @click.prevent.stop="goTo({{ $idx }})" :aria-label="'View image ' + ({{ $idx }} + 1)" :aria-current="activeIndex === {{ $idx }} ? 'true' : 'false'"
                        class="h-1.5 w-1.5 rounded-full transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-1 focus-visible:ring-offset-black/10"
                        :class="activeIndex === {{ $idx }} ? 'bg-gray-800 dark:bg-white w-3' : 'bg-gray-400/60 dark:bg-white/60 hover:bg-gray-600/80 dark:hover:bg-white/80'">
                    </button>
                @endforeach
            </div>
            <div class="pointer-events-none absolute bottom-2 right-2 z-[2] flex md:hidden items-center rounded-full bg-black/55 px-2 py-1 text-[10px] font-medium leading-none text-white backdrop-blur-sm" x-show="hasGallery" x-cloak>
                <span x-text="(activeIndex + 1) + ' / ' + total"></span>
            </div>
        @endif
    </div>

    {{-- Product information — premium, scannable, compact with consistent height --}}
    <div class="flex flex-1 flex-col p-3 pt-2.5 gap-1.5">
        @if ($brandName)
            <p class="truncate text-[11px] font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                {{ $brandName }}
            </p>
        @endif

        <a href="{{ $card['url'] }}" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 rounded-sm">
            <h3 class="line-clamp-2 min-h-[2.45rem] text-[13px] font-medium leading-snug text-gray-900 dark:text-gray-100">
                {{ $card['name'] }}
            </h3>
        </a>

        {{-- Rating — reserved height --}}
        <div class="min-h-[1.1rem] flex items-center">
            @if (($reviews ?? 0) > 0 && $rating !== null)
                <div class="flex items-center gap-1">
                    <span class="text-amber-500 text-[13px]" aria-hidden="true">★</span>
                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ number_format((float) $rating, 1) }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ $reviews }})</span>
                </div>
            @endif
        </div>

        {{-- Price row — reserved height, discount inline (tight mobile to prevent wrap) --}}
        <div class="flex items-baseline gap-1 flex-wrap min-h-[1.35rem]">
            @if ($variant)
                <span class="text-[13px] sm:text-[15px] font-bold leading-none tracking-tight text-gray-900 dark:text-gray-50 whitespace-nowrap tabular-nums">{{ money_without_trailing_zeros((int) $variant['price']) }}</span>
                @if ($variant['compare_at_price'] && $variant['compare_at_price'] > $variant['price'])
                    <span class="text-[11px] sm:text-xs font-normal leading-none text-gray-400 line-through whitespace-nowrap tabular-nums">{{ money_without_trailing_zeros((int) $variant['compare_at_price']) }}</span>
                    @if ($discount)
                        <span class="text-[11px] sm:text-xs font-bold leading-none text-red-600 whitespace-nowrap">-{{ $discount }}%</span>
                    @endif
                @endif
            @else
                <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Price unavailable') }}</span>
            @endif
        </div>

        {{-- CTA — anchored at bottom --}}
        <div class="mt-auto pt-2">
            @if ($cta && $cta['type'] === 'add_to_cart')
                <button type="button" @click="$store.cart.add({{ $cta['variant_id'] }})"
                    :disabled="$store.cart.pending[{{ $cta['variant_id'] }}]"
                    :aria-busy="$store.cart.pending[{{ $cta['variant_id'] }}] ? 'true' : 'false'"
                    aria-label="{{ $cta['label'] }} {{ $card['name'] }}"
                    class="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-[var(--brand)] px-3 text-xs font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="!$store.cart.pending[{{ $cta['variant_id'] }}]" class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 9.465A2.25 2.25 0 0119.245 21H4.755a2.25 2.25 0 01-2.243-2.532l1.263-9.465A1.5 1.5 0 014.26 7.5h15.48a1.5 1.5 0 011.486 1.507z" />
                        </svg>
                        {{ $cta['label'] }}
                    </span>
                    <span x-show="$store.cart.pending[{{ $cta['variant_id'] }}]" x-cloak class="inline-flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        {{ __('Adding…') }}
                    </span>
                </button>
            @elseif ($cta && $cta['type'] === 'select_options')
                <x-storefront.variant-modal :card="$card">
                    <x-slot:trigger>
                        <button type="button" @click="open = true"
                            class="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-[var(--brand)] px-3 text-xs font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:cursor-not-allowed disabled:opacity-60">
                            <span>{{ __('Add to Cart') }}</span>
                        </button>
                    </x-slot:trigger>
                </x-storefront.variant-modal>
            @elseif ($cta && $cta['type'] === 'disabled')
                <button type="button" disabled
                    class="flex h-10 w-full cursor-not-allowed items-center justify-center rounded-xl bg-gray-100 px-3 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                    {{ $cta['label'] }}
                </button>
            @else
                <div class="h-10" aria-hidden="true"></div>
            @endif
        </div>
    </div>
</div>
