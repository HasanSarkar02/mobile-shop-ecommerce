@php
    $banners = \App\Models\Banner::query()
        ->where('placement', $section->config['placement'] ?? 'hero')
        ->when($section->campaign_id, fn($q) => $q->where('campaign_id', $section->campaign_id))
        ->currentlyActive()
        ->orderBy('sort_order')
        ->get();
@endphp
@if ($banners->isNotEmpty())
    <div
        x-data="{
            active: 0,
            total: {{ $banners->count() }},
            timer: null,
            progress: 0,
            paused: false,
            init() {
                if (this.total <= 1) return;
                this.start();
                // Pause on hover/focus
                this.$el.addEventListener('mouseenter', () => this.pause());
                this.$el.addEventListener('mouseleave', () => this.resume());
                this.$el.addEventListener('focusin', () => this.pause());
                this.$el.addEventListener('focusout', () => this.resume());
            },
            start() {
                this.clear();
                this.progress = 0;
                const step = 100 / (5000 / 50);
                this.timer = setInterval(() => {
                    if (this.paused || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    this.progress += step;
                    if (this.progress >= 100) {
                        this.progress = 0;
                        this.active = (this.active + 1) % this.total;
                    }
                }, 50);
            },
            clear() { if (this.timer) clearInterval(this.timer); },
            pause() { this.paused = true; },
            resume() { this.paused = false; },
            go(i) { this.active = i; this.progress = 0; },
            next() { this.active = (this.active + 1) % this.total; this.progress = 0; },
            prev() { this.active = (this.active - 1 + this.total) % this.total; this.progress = 0; },
        }"
        x-init="init()"
        @keydown.arrow-right.prevent="next()"
        @keydown.arrow-left.prevent="prev()"
        tabindex="0"
        role="region"
        aria-roledescription="carousel"
        aria-label="Featured banners"
        class="group relative w-full rounded-2xl overflow-hidden aspect-[16/9] md:aspect-[21/9] max-h-[clamp(180px,38vh,280px)] md:max-h-[clamp(320px,42vh,520px)] lg:max-h-[clamp(320px,56vh,520px)] ring-1 ring-black/[0.06] shadow-soft bg-gray-100 dark:bg-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
    >
        <div class="relative w-full h-full aspect-[16/9] md:aspect-[21/9] max-h-[clamp(180px,38vh,280px)] md:max-h-[clamp(320px,42vh,520px)] lg:max-h-[clamp(320px,56vh,520px)] overflow-hidden">
            @foreach ($banners as $i => $banner)
                <a href="{{ $banner->resolveUrl() ?? '#' }}"
                    x-show="active === {{ $i }}"
                    x-transition:enter="transition ease-out duration-700"
                    x-transition:enter-start="opacity-0 scale-[1.02]"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 block"
                    :aria-hidden="active !== {{ $i }}"
                    x-cloak
                    @if($i !== 0) style="display:none" @endif
                >
                    <div class="relative w-full h-full max-h-[clamp(180px,38vh,280px)] md:max-h-[clamp(320px,42vh,520px)] lg:max-h-[clamp(320px,56vh,520px)] overflow-hidden bg-gray-100 dark:bg-gray-800">
                        @if ($banner->media_type->value === 'video')
                            <video src="{{ $banner->getFirstMediaUrl('video') }}" autoplay muted loop playsinline
                                class="w-full h-full max-h-[clamp(180px,38vh,280px)] md:max-h-[clamp(320px,42vh,520px)] lg:max-h-[clamp(320px,56vh,520px)] object-cover object-center"></video>
                        @else
                            <img src="{{ $banner->getFirstMediaUrl('image', 'large') }}"
                                alt="{{ $banner->title }}"
                                loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                decoding="async"
                                class="w-full h-full max-h-[clamp(180px,38vh,280px)] md:max-h-[clamp(320px,42vh,520px)] lg:max-h-[clamp(320px,56vh,520px)] object-cover object-center transition duration-[2000ms] ease-out will-change-transform group-[.is-hovered]:scale-[1.02]"
                                x-init="$el.classList.add('is-loaded')">
                        @endif
                        {{-- Subtle vignette for depth — title/shop name removed per request --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-60 pointer-events-none"></div>
                        {{-- Hairline inner stroke for refined edge --}}
                        <div class="absolute inset-0 ring-inset ring-1 ring-white/10 pointer-events-none rounded-2xl"></div>
                    </div>
                </a>
                {{-- Fallback for no-JS: first banner visible --}}
                <noscript>
                    @if($i === 0)
                        <a href="{{ $banner->resolveUrl() ?? '#' }}" class="block">
                            <img src="{{ $banner->getFirstMediaUrl('image', 'large') }}" alt="{{ $banner->title }}" class="w-full aspect-[16/9] md:aspect-[21/9] max-h-[clamp(180px,38vh,280px)] md:max-h-[clamp(320px,42vh,520px)] lg:max-h-[clamp(320px,56vh,520px)] object-cover">
                        </a>
                    @endif
                </noscript>
            @endforeach
        </div>

        @if ($banners->count() > 1)
            {{-- Progress dots — editorial, not generic circles --}}
            <div class="absolute bottom-3 sm:bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 px-2.5 py-1.5 rounded-full bg-black/35 backdrop-blur-md border border-white/10 shadow-soft">
                @foreach ($banners as $i => $banner)
                    <button @click="go({{ $i }})"
                        :aria-current="active === {{ $i }} ? 'true' : 'false'"
                        :aria-label="'Go to slide ' + ({{ $i }}+1)"
                        class="group/dot relative overflow-hidden rounded-full transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        :class="active === {{ $i }} ? 'w-10 h-1.5 bg-white' : 'w-1.5 h-1.5 bg-white/60 hover:bg-white/90'"
                    >
                        <span x-show="active === {{ $i }}" class="absolute inset-0 bg-[var(--brand)] origin-left" :style="`width: ${progress}%`"></span>
                    </button>
                @endforeach
            </div>

            {{-- Arrow controls — refined, not templated chevrons --}}
            <button @click="prev()" aria-label="Previous slide"
                class="absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-white/90 backdrop-blur border border-white/20 shadow-soft flex items-center justify-center text-gray-900 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 hover:bg-white hover:scale-105 transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button @click="next()" aria-label="Next slide"
                class="absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-white/90 backdrop-blur border border-white/20 shadow-soft flex items-center justify-center text-gray-900 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 hover:bg-white hover:scale-105 transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Slide counter — editorial label --}}
            <div class="absolute top-3 right-3 hidden sm:flex items-center gap-1.5 rounded-full bg-black/35 backdrop-blur border border-white/10 px-2.5 py-1 text-[11px] font-medium tracking-wide text-white">
                <span x-text="String(active+1).padStart(2,'0')"></span>
                <span class="opacity-50">/</span>
                <span class="opacity-75">{{ str_pad((string)$banners->count(), 2, '0', STR_PAD_LEFT) }}</span>
            </div>
        @endif
    </div>
@endif
