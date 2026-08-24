{{-- Shared PDP gallery primitive (F.4). Extracted verbatim from
     resources/views/storefront/products/show.blade.php. Reads the surrounding
     productDetail(...) Alpine scope through the DOM hierarchy (Blade
     components inline their markup, so scoping is unchanged):
     currentImages(), resolvedActiveImage(), markLoaded(), markErrored(),
     isLoaded(), hasUsableImage(), activeImage, lightboxOpen,
     loadedImages, erroredImages. --}}
<div>
    <button type="button" @click="lightboxOpen = true" :disabled="!hasUsableImage()"
        class="block w-full aspect-square rounded-2xl overflow-hidden bg-gray-100 dark:bg-gray-900 relative group cursor-zoom-in disabled:cursor-default"
        aria-label="Open full-size image">
        {{-- Driven purely by currentImages().length, never by activeImage — so this
             can never go blank even if activeImage momentarily desyncs. --}}
        <div x-show="currentImages().length === 0" x-cloak
            class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-gray-300 dark:text-gray-600">
            <x-ui.icon name="image" class="w-16 h-16" />
            <span class="text-sm font-medium text-gray-400 dark:text-gray-500">No image available</span>
        </div>

        <template x-for="image in currentImages()" :key="image.src">
            <img x-show="image.src === resolvedActiveImage() && !erroredImages[image.src]"
                x-transition:enter="transition-opacity duration-200 ease-out"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" :src="image.src"
                :alt="image.alt" width="600" height="600" loading="eager" x-init="$el.complete && $el.naturalWidth > 0 && markLoaded(image.src)"
                x-on:load="markLoaded(image.src)" x-on:error="markErrored(image.src)"
                class="w-full h-full object-cover">
        </template>

        <div x-show="currentImages().length > 0 && !isLoaded(resolvedActiveImage()) && !erroredImages[resolvedActiveImage()]"
            x-cloak class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-900">
            <div class="h-9 w-9 rounded-full border-2 border-gray-300 dark:border-gray-700 border-t-[var(--brand)] animate-spin"
                aria-hidden="true"></div>
        </div>

        <div x-show="currentImages().length > 0 && erroredImages[resolvedActiveImage()]" x-cloak
            class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-gray-300 dark:text-gray-600">
            <x-ui.icon name="image" class="w-16 h-16" />
            <span class="text-sm font-medium text-gray-400 dark:text-gray-500">Image unavailable</span>
        </div>

        <span x-show="hasUsableImage()"
            class="absolute bottom-3 right-3 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 shadow-soft opacity-0 group-hover:opacity-100 transition">
            <x-ui.icon name="search" class="w-4 h-4" />
        </span>
    </button>
    <div class="flex gap-2 mt-4 overflow-x-auto pb-1">
        <template x-for="image in currentImages()" :key="'thumb-' + image.src">
            <button @click="activeImage = image.src"
                class="w-16 h-16 rounded-lg overflow-hidden border-2 flex-shrink-0 transition"
                :class="resolvedActiveImage() === image.src ? 'border-[var(--brand)]' : 'border-transparent'">
                <img :src="image.src" :alt="image.alt" width="64" height="64" loading="lazy"
                    class="w-full h-full object-cover">
            </button>
        </template>
    </div>
</div>

{{-- Lightbox --}}
<template x-teleport="body">
    <div x-show="lightboxOpen" x-cloak
        class="fixed inset-0 z-[70] bg-black/90 flex items-center justify-center p-4 sm:p-10" role="dialog"
        aria-modal="true" aria-label="Product image" @keydown.escape.window="lightboxOpen = false"
        @click="lightboxOpen = false">
        <button type="button" @click.stop="lightboxOpen = false"
            class="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition"
            aria-label="Close">
            <x-ui.icon name="close" class="w-6 h-6" />
        </button>
        <template x-for="image in currentImages()" :key="'lightbox-' + image.src">
            <img x-show="image.src === resolvedActiveImage()" :src="image.src" :alt="image.alt"
                @click.stop class="max-w-full max-h-full object-contain rounded-lg">
        </template>
        <template x-if="currentImages().length > 1">
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2" @click.stop>
                <template x-for="image in currentImages()" :key="'lightbox-dot-' + image.src">
                    <button @click="activeImage = image.src" class="w-2 h-2 rounded-full transition"
                        :class="resolvedActiveImage() === image.src ? 'bg-white' : 'bg-white/40'"
                        aria-label="Show image"></button>
                </template>
            </div>
        </template>
    </div>
</template>
