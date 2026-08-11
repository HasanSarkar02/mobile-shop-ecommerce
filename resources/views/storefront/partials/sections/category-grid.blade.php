@php
    $renderer = app(\App\Services\Storefront\HomepageSectionRenderer::class);
    $isBrandSource = ($section->config['source'] ?? 'category') === 'brand';
    $items = $isBrandSource ? $renderer->resolveBrands($section) : $renderer->resolveCategories($section);

    // Deterministic pastel palette for fallback tiles — looks intentional, not "empty"
    $palette = [
        ['bg' => 'bg-orange-50 dark:bg-orange-950/40', 'text' => 'text-orange-400 dark:text-orange-500'],
        ['bg' => 'bg-blue-50 dark:bg-blue-950/40', 'text' => 'text-blue-400 dark:text-blue-500'],
        ['bg' => 'bg-emerald-50 dark:bg-emerald-950/40', 'text' => 'text-emerald-400 dark:text-emerald-500'],
        ['bg' => 'bg-violet-50 dark:bg-violet-950/40', 'text' => 'text-violet-400 dark:text-violet-500'],
        ['bg' => 'bg-rose-50 dark:bg-rose-950/40', 'text' => 'text-rose-400 dark:text-rose-500'],
    ];
@endphp
@if ($items->isNotEmpty())
    <div>
        @if ($isBrandSource)
            {{-- Brand carousel: horizontal scroll with snap, arrows and keyboard nav --}}
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
            <div
                x-data="{
                    canPrev: false,
                    canNext: false,
                    init() {
                        this.update();
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
                    scrollBy(dir) {
                        const el = this.$refs.track;
                        const card = el.querySelector(':scope > a');
                        const step = (card ? card.getBoundingClientRect().width + this.gap() : el.clientWidth) * dir;
                        el.scrollBy({ left: step, behavior: 'smooth' });
                    }
                }">
                <div class="flex justify-between items-center gap-4 mb-5">
                    @if ($section->title)
                        <h2 class="text-xl font-bold tracking-tight">{{ $section->title }}</h2>
                    @endif
                    <div class="flex items-center gap-1.5 flex-shrink-0 -mr-1">
                        <button type="button" @click="scrollBy(-1)" x-show="canPrev" x-cloak
                            aria-label="Previous brands"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:border-[var(--brand)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button type="button" @click="scrollBy(1)" x-show="canNext" x-cloak
                            aria-label="Next brands"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:border-[var(--brand)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        @if ($section->link_type->value !== 'none')
                            <a href="{{ $section->resolveUrl() }}"
                                class="ml-1 text-sm font-medium text-[var(--brand)] hover:underline underline-offset-2">View all</a>
                        @endif
                    </div>
                </div>

                <div x-ref="track" tabindex="0" role="region" aria-label="Brand carousel"
                    @scroll.passive="update()"
                    @keydown.arrow-left.prevent="scrollBy(-1)"
                    @keydown.arrow-right.prevent="scrollBy(1)"
                    class="carousel-track-scroll flex gap-4 sm:gap-5 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                    @foreach ($items as $item)
                        @php
                            $swatch = $palette[crc32($item->name) % count($palette)];
                            $domId = 'tile-b-' . $item->id;
                        @endphp
                        <a href="{{ route('storefront.brand', $item->slug) }}"
                            class="group flex flex-col items-center gap-2.5 text-center shrink-0 snap-start
                                w-[calc(50%-0.5rem)] sm:w-[calc(33.333%-0.834rem)] md:w-[calc(25%-0.938rem)] lg:w-[calc(20%-1rem)] xl:w-[calc(16.667%-1.042rem)]">
                            <div
                                class="relative w-full aspect-square rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-800 flex items-center justify-center transition-all duration-300 group-hover:border-[var(--brand)] group-hover:shadow-soft group-hover:-translate-y-0.5 bg-white">

                                {{-- Fallback: always in DOM, shown by default, hidden by JS if the image loads --}}
                                <div id="{{ $domId }}-fallback"
                                    class="absolute inset-0 flex items-center justify-center {{ $swatch['bg'] }}">
                                    <span class="text-2xl font-bold {{ $swatch['text'] }}">
                                        {{ mb_substr($item->name, 0, 1) }}
                                    </span>
                                </div>

                                @if ($item->logo_path)
                                    <img src="{{ asset('storage/' . $item->logo_path) }}" alt="{{ $item->name }}" loading="lazy"
                                        decoding="async"
                                        class="relative w-full h-full object-contain p-5 opacity-0 transition-all duration-500 ease-out scale-95 group-hover:scale-100"
                                        onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                        onerror="this.remove();">
                                @endif
                            </div>
                            <span
                                class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-[var(--brand)] transition-colors line-clamp-1"
                                title="{{ $item->name }}">
                                {{ $item->name }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Category grid --}}
            <div class="flex justify-between items-end mb-5">
                @if ($section->title)
                    <h2 class="text-xl font-bold tracking-tight">{{ $section->title }}</h2>
                @endif
                @if ($section->link_type->value !== 'none')
                    <a href="{{ $section->resolveUrl() }}"
                        class="text-sm font-medium text-[var(--brand)] hover:underline underline-offset-2">View all</a>
                @endif
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4 sm:gap-5">
                @foreach ($items as $item)
                    @php
                        $swatch = $palette[crc32($item->name) % count($palette)];
                        $domId = 'tile-c-' . $item->id;
                    @endphp
                    <a href="{{ route('storefront.category', $item->slug) }}"
                        class="group flex flex-col items-center gap-2.5 text-center">
                        <div
                            class="relative w-full aspect-square rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-800 flex items-center justify-center transition-all duration-300 group-hover:border-[var(--brand)] group-hover:shadow-soft group-hover:-translate-y-0.5 bg-gray-50 dark:bg-gray-900">

                            {{-- Fallback: always in DOM, shown by default, hidden by JS if the image loads --}}
                            <div id="{{ $domId }}-fallback"
                                class="absolute inset-0 flex items-center justify-center {{ $swatch['bg'] }}">
                                <span class="text-2xl font-bold {{ $swatch['text'] }}">
                                    {{ mb_substr($item->name, 0, 1) }}
                                </span>
                            </div>

                            @if ($item->image_path)
                                <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->name }}" loading="lazy"
                                    decoding="async"
                                    class="relative w-full h-full object-contain p-4 opacity-0 transition-all duration-500 ease-out scale-95 group-hover:scale-100"
                                    onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                    onerror="this.remove();">
                            @endif
                        </div>
                        <span
                            class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-[var(--brand)] transition-colors line-clamp-1"
                            title="{{ $item->name }}">
                            {{ $item->name }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif