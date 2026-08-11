@php
    $popupBanner = \App\Models\Banner::query()
        ->where('placement', 'popup')
        ->currentlyActive()
        ->orderBy('sort_order')
        ->get()
        ->first();
    $popupUrl = $popupBanner?->resolveUrl();
@endphp

@if ($popupBanner)
    <div x-data="{
        open: true,
        init() {
            const frequency = '{{ $popupBanner->popup_frequency->value }}';
            if (frequency === 'once_per_session' && sessionStorage.getItem('popup_dismissed')) {
                this.open = false;
            }
            if (frequency === 'once_per_day' && localStorage.getItem('popup_dismissed_date') === '{{ now()->toDateString() }}') {
                this.open = false;
            }
            if (this.open) {
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => this.$refs.closeButton?.focus());
            }
        },
        close() {
            const frequency = '{{ $popupBanner->popup_frequency->value }}';
            if (frequency === 'once_per_session') {
                sessionStorage.setItem('popup_dismissed', '1');
            }
            if (frequency === 'once_per_day') {
                localStorage.setItem('popup_dismissed_date', '{{ now()->toDateString() }}');
            }
            this.open = false;
            document.body.style.overflow = '';
        }
    }" @keydown.escape.window="close()" x-cloak>
        <div x-show="open"
            x-transition.opacity
            class="{{ $popupBanner->visibility->value === 'desktop' ? 'hidden md:flex' : ($popupBanner->visibility->value === 'mobile' ? 'flex md:hidden' : 'flex') }} fixed inset-0 z-[100] items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-label="{{ $popupBanner->title }}">
            <div class="absolute inset-0 bg-black/60" @click="close()" aria-hidden="true"></div>

            <div
                class="relative w-full max-w-lg sm:max-w-2xl rounded-2xl overflow-hidden bg-white dark:bg-gray-900 shadow-2xl">
                @if ($popupUrl)
                    <a href="{{ $popupUrl }}" class="block">
                @endif
                    @if ($popupBanner->media_type->value === 'video')
                        <video src="{{ $popupBanner->getFirstMediaUrl('video') }}" autoplay muted loop
                            playsinline class="w-full aspect-[4/3] sm:aspect-[16/9] object-cover"></video>
                    @else
                        <img src="{{ $popupBanner->getFirstMediaUrl('image', 'large') }}" alt="{{ $popupBanner->title }}"
                            class="w-full aspect-[4/3] sm:aspect-[16/9] object-cover">
                    @endif
                @if ($popupUrl)
                    </a>
                @endif

                <button type="button" x-ref="closeButton" @click="close()"
                    class="absolute top-3 right-3 z-10 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-800 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                    aria-label="Close popup">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endif