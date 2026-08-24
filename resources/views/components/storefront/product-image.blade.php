@props([
    'src',
    'alt' => '',
    'dimmed' => false,
    'gallery' => [],
    'hoverEnabled' => false,
])

{{-- Shared product-image primitive (F.4 + F.6): skeleton until decode,
     broken-image fallback, polished placeholder when there is no image at
     all. F.6 adds an opt-in desktop-only hover-preview gallery: when
     hoverEnabled is true and the product has multiple images, hovering the
     card advances through the gallery on desktop; mouse-leave restores the
     primary image. Touch devices never depend on hover — the guard checks
     `(hover: hover) and (pointer: fine)` before starting the timer, and the
     primary image is always the fallback when JS is off. --}}
@php
    $hasGallery = $hoverEnabled && is_array($gallery) && count($gallery) > 1;
    // Limit gallery to 5 server-side already, but double-guard here.
    $gallery = $hasGallery ? array_values(array_slice($gallery, 0, 5)) : [];
@endphp
<div class="relative aspect-square overflow-hidden bg-gray-50 dark:bg-gray-800/60"
    x-data="{
        imgLoaded: false,
        imgError: false,
        hoverIndex: 0,
        hoverTimer: null,
        canHover() { return window.matchMedia('(hover: hover) and (pointer: fine)').matches; },
        startHover() {
            if (!@js($hasGallery) || !this.canHover()) return;
            if (this.hoverTimer) return;
            const len = @js(count($gallery));
            if (len <= 1) return;
            this.hoverTimer = setInterval(() => { this.hoverIndex = (this.hoverIndex + 1) % len; }, 850);
        },
        stopHover() {
            if (this.hoverTimer) { clearInterval(this.hoverTimer); this.hoverTimer = null; }
            this.hoverIndex = 0;
        }
    }"
    @mouseenter="startHover()"
    @mouseleave="stopHover()"
>
    @if ($src)
        {{-- Skeleton shows until the image decodes; disappears the moment it loads or errors. --}}
        <div x-show="!imgLoaded && !imgError" x-cloak
            class="absolute inset-0 animate-pulse bg-gray-100 dark:bg-gray-800"></div>

        @if ($hasGallery)
            {{-- Hover gallery: primary (index 0) + layered previews. Primary is
                 always rendered so the card is never blank without JS. --}}
            <img src="{{ $src }}" alt="{{ $alt }}" width="400" height="400" loading="lazy"
                decoding="async" x-init="$el.complete && $el.naturalWidth > 0 && (imgLoaded = true)" x-on:load="imgLoaded = true" x-on:error="imgError = true"
                x-show="!imgError && hoverIndex === 0"
                class="h-full w-full object-cover transition duration-300 {{ $dimmed ? 'opacity-60 grayscale' : 'group-hover:scale-[1.04]' }}">

            @foreach ($gallery as $idx => $g)
                @if ($idx === 0) @continue @endif
                <img src="{{ $g['src'] }}" alt="{{ $g['alt'] }}" width="400" height="400" loading="lazy" decoding="async"
                    x-show="!imgError && hoverIndex === {{ $idx }}"
                    x-cloak
                    class="absolute inset-0 h-full w-full object-cover transition duration-300 {{ $dimmed ? 'opacity-60 grayscale' : '' }}">
            @endforeach
        @else
            <img src="{{ $src }}" alt="{{ $alt }}" width="400" height="400" loading="lazy"
                decoding="async" x-init="$el.complete && $el.naturalWidth > 0 && (imgLoaded = true)" x-on:load="imgLoaded = true" x-on:error="imgError = true"
                x-show="!imgError"
                class="h-full w-full object-cover transition duration-300 {{ $dimmed ? 'opacity-60 grayscale' : 'group-hover:scale-[1.04]' }}">
        @endif

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

    {{-- Overlay slot: badges / stock overlays positioned within the image area. --}}
    {{ $slot }}
</div>
