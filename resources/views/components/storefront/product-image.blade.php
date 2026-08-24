@props([
    'src',
    'alt' => '',
    'dimmed' => false,
])

{{-- Shared product-image primitive (F.4): skeleton until decode, broken-image
     fallback, polished placeholder when there is no image at all. Extracted
     verbatim from storefront/partials/product-card.blade.php. --}}
<div class="relative aspect-square overflow-hidden bg-gray-50 dark:bg-gray-800/60"
    x-data="{ imgLoaded: false, imgError: false }">
    @if ($src)
        {{-- Skeleton shows until the image decodes; disappears the moment it loads or errors. --}}
        <div x-show="!imgLoaded && !imgError" x-cloak
            class="absolute inset-0 animate-pulse bg-gray-100 dark:bg-gray-800"></div>

        <img src="{{ $src }}" alt="{{ $alt }}" width="400" height="400" loading="lazy"
            decoding="async" x-init="$el.complete && $el.naturalWidth > 0 && (imgLoaded = true)" x-on:load="imgLoaded = true" x-on:error="imgError = true"
            x-show="!imgError"
            class="h-full w-full object-cover transition duration-300 {{ $dimmed ? 'opacity-60 grayscale' : 'group-hover:scale-[1.04]' }}">

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
