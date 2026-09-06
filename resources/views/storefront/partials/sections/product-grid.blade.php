<style>
    .carousel-track-scroll {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .carousel-track-scroll::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
    }
</style>
@php
    $products = app(\App\Services\Storefront\HomepageSectionRenderer::class)->resolveProducts($section);
    $wishlistedIds = app(\App\Services\WishlistService::class)->wishlistedProductIds($products->pluck('id'));
    $cards = app(\App\Services\Storefront\ProductCardData::class)->forMany($products, $wishlistedIds);
    $showArrows = $cards->count() > 4;
@endphp
@if ($cards->isNotEmpty())
    <div
        class="-mt-4 sm:-mt-2"
        x-data="{
            canPrev: false,
            canNext: false,
            init() {
                this.$nextTick(() => this.update());
            },
            update() {
                const el = this.$refs.track;
                this.canPrev = el.scrollLeft > 4;
                this.canNext = el.scrollLeft < el.scrollWidth - el.clientWidth - 4;
            },
            gap() {
                const el = this.$refs.track;
                const style = window.getComputedStyle(el);
                const colGap = parseFloat(style.columnGap || style.gap || '0');
                if (!isNaN(colGap) && colGap > 0) return colGap;
                if (el.children.length > 1) {
                    const isGrid = el.classList.contains('grid');
                    const idx = isGrid && el.children.length > 2 ? 2 : 1;
                    if (el.children.length > idx) {
                        return el.children[idx].offsetLeft - el.children[0].offsetLeft - el.children[0].offsetWidth;
                    }
                    return el.children[1].offsetLeft - el.children[0].offsetLeft - el.children[0].offsetWidth;
                }
                return 0;
            },
            reduced() {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            },
            scrollBy(dir) {
                const el = this.$refs.track;
                const card = el.querySelector(':scope > div');
                const step = (card ? card.getBoundingClientRect().width + this.gap() : el.clientWidth) * dir;
                el.scrollBy({ left: step, behavior: this.reduced() ? 'auto' : 'smooth' });
            }
        }">
        <div class="flex justify-between items-center gap-4 mb-4">
            @php $displayTitle = trim((string) ($section->title ?? '')) !== '' ? $section->title : \App\Support\IndustryConfig::currentGet('homepage.product_grid_title', 'Featured Products'); @endphp
            @if ($displayTitle)
                <h2 class="text-xl font-bold">{{ $displayTitle }}</h2>
            @endif
            <div class="flex items-center gap-1.5 flex-shrink-0 -mr-1">
                @if ($showArrows)
                    <button type="button" @click="scrollBy(-1)" x-show="canPrev" x-cloak
                        aria-label="Previous products"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:border-[var(--brand)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button type="button" @click="scrollBy(1)" x-show="canNext" x-cloak
                        aria-label="Next products"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:border-[var(--brand)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                @endif
                @if ($section->link_type->value !== 'none')
                    <a href="{{ $section->resolveUrl() }}" class="ml-1 text-sm text-[var(--brand)]">View all</a>
                @endif
            </div>
        </div>

        @php
            $rows = \App\Support\HomepagePresentation::rows($section);
            $isGrocery = \App\Support\IndustryConfig::currentGet('ui.card_component') === 'storefront.product-cards.grocery';
            // Production: correct gap math for 6-col (100% -5*1.25)/6 = 16.666% -1.042rem, grocery needs 6 at lg, others 4 at lg
            $trackClass = $rows === 2
                ? 'carousel-track-scroll grid grid-flow-col grid-rows-2 auto-cols-[calc(50%-0.5rem)] sm:auto-cols-[calc(33.333%-0.834rem)] md:auto-cols-[calc(25%-0.938rem)] lg:auto-cols-[calc(20%-1rem)] xl:auto-cols-[calc(16.666%-1.042rem)] gap-4 sm:gap-5 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]'
                : ($isGrocery
                    ? 'carousel-track-scroll flex gap-4 sm:gap-5 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]'
                    : 'carousel-track-scroll flex gap-4 sm:gap-5 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]');
        @endphp
        <div x-ref="track" tabindex="0" role="region" aria-label="{{ $section->title ?: 'Products' }} carousel"
            @scroll.passive="update()"
            @keydown.arrow-left.prevent="scrollBy(-1)"
            @keydown.arrow-right.prevent="scrollBy(1)"
            class="{{ $trackClass }}">
            @foreach ($cards as $card)
                <div class="{{ $rows === 2 ? 'snap-start min-w-0' : ($isGrocery ? 'shrink-0 snap-start w-[calc(50%-0.5rem)] sm:w-[calc(33.333%-0.834rem)] md:w-[calc(25%-0.938rem)] lg:w-[calc(20%-1rem)] xl:w-[calc(16.666%-1.042rem)]' : 'shrink-0 snap-start w-[calc(50%-0.5rem)] sm:w-[calc(33.333%-0.834rem)] md:w-[calc(25%-0.938rem)] lg:w-[calc(25%-0.938rem)]') }}">
                    <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
                </div>
            @endforeach
        </div>
    </div>
@endif
