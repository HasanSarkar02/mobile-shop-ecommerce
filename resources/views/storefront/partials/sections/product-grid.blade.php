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
    $showArrows = $products->count() > 4;
@endphp
@if ($products->isNotEmpty())
    <div
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
                if (el.children.length > 1) {
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
            @if ($section->title)
                <h2 class="text-xl font-bold">{{ $section->title }}</h2>
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

        <div x-ref="track" tabindex="0" role="region" aria-label="{{ $section->title ?: 'Products' }} carousel"
            @scroll.passive="update()"
            @keydown.arrow-left.prevent="scrollBy(-1)"
            @keydown.arrow-right.prevent="scrollBy(1)"
            class="carousel-track-scroll flex gap-4 sm:gap-5 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
            @foreach ($products as $product)
                <div class="shrink-0 snap-start w-[calc(50%-0.5rem)] sm:w-[calc(33.333%-0.834rem)] md:w-[calc(25%-0.938rem)]">
                    @include('storefront.partials.product-card', ['product' => $product])
                </div>
            @endforeach
        </div>
    </div>
@endif