@props(['aspect' => 'aspect-[4/3] lg:aspect-square'])
<div class="flex flex-col-reverse lg:flex-row gap-3 lg:gap-5 w-full min-w-0">
    <div
        class="flex lg:flex-col gap-2.5 overflow-x-auto lg:overflow-y-auto lg:w-[88px] shrink-0 lg:max-h-[520px] pb-1 lg:pb-0 -mx-1 px-1 lg:mx-0 lg:px-0 scrollbar-thin w-full lg:w-[88px]">
        <template x-for="(image, idx) in currentImages().slice(0, 5)" :key="'thumb-' + image.src">
            <button @click="activeImage = image.src"
                class="w-[68px] h-[68px] lg:w-[80px] lg:h-[80px] rounded-xl overflow-hidden border-2 flex-shrink-0 transition bg-white dark:bg-gray-900 flex items-center justify-center p-1.5 shadow-sm"
                :class="resolvedActiveImage() === image.src ? 'border-[var(--brand)] shadow-md' : 'border-gray-100 dark:border-gray-800 hover:border-gray-200'">
                <img :src="image.src" :alt="image.alt" width="68" height="68" loading="lazy"
                    class="max-w-full max-h-full w-auto h-auto object-contain object-center">
            </button>
        </template>
        <template x-if="currentImages().length > 5">
            <button @click="lightboxOpen = true"
                class="w-[68px] h-[68px] lg:w-[80px] lg:h-[80px] rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 flex-shrink-0 bg-gray-900 text-white flex flex-col items-center justify-center gap-0.5 text-xs font-semibold shadow-sm">
                <span x-text="'+' + (currentImages().length - 5)"></span>
                <span class="text-[10px] font-normal opacity-80">More</span>
            </button>
        </template>
    </div>
    <div x-data="{ 
            zoom: false, 
            mouseX: 50, 
            mouseY: 50,
            handleMouseMove(e) {
                if (!this.hasUsableImage() || window.matchMedia('(pointer: coarse)').matches) return;
                const rect = this.$el.getBoundingClientRect();
                const x = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                const y = Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height));
                this.mouseX = x * 100;
                this.mouseY = y * 100;
            }
        }"
        @mouseenter="if(!window.matchMedia('(pointer: coarse)').matches) zoom = true"
        @mouseleave="zoom = false"
        @mousemove="handleMouseMove"
        role="button" tabindex="0" @click="hasUsableImage() && (lightboxOpen = true)"
        @keydown.enter.prevent="hasUsableImage() && (lightboxOpen = true)"
        @keydown.space.prevent="hasUsableImage() && (lightboxOpen = true)"
        @touchstart.passive="onGalleryTouchStart($event)" @touchmove.passive="onGalleryTouchMove($event)"
        @touchend="onGalleryTouchEnd($event)" @keydown.arrow-left.prevent="galleryPrev()"
        @keydown.arrow-right.prevent="galleryNext()"
        class="flex-1 min-w-0 {{ $aspect }} bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl flex items-center justify-center relative isolate group select-none touch-pan-y max-h-[560px] w-full overflow-hidden"
        :class="hasUsableImage() ? (zoom ? 'cursor-zoom-out' : 'cursor-zoom-in') : 'cursor-default'" aria-label="Open full-size image">
        <div x-show="currentImages().length === 0" x-cloak
            class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-gray-300 dark:text-gray-600">
            <x-ui.icon name="image" class="w-16 h-16" />
            <span class="text-sm font-medium text-gray-400 dark:text-gray-500">No image available</span>
        </div>

        <template x-for="image in currentImages()" :key="image.src">
            <img x-show="image.src === resolvedActiveImage() && !erroredImages[image.src]"
                x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" :src="image.src" :alt="image.alt" width="800"
                height="800" loading="eager" x-init="$el.complete && $el.naturalWidth > 0 && markLoaded(image.src)" x-on:load="markLoaded(image.src)"
                x-on:error="markErrored(image.src)" 
                class="w-full h-full object-contain object-center p-6 lg:p-8 transition-transform duration-100 ease-out"
                :class="zoom ? 'scale-[1.85]' : 'scale-100'"
                :style="zoom ? { 'transform-origin': mouseX + '% ' + mouseY + '%' } : {}">
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

        {{-- Previous / Next arrows — main gallery (inset inside image area, not viewport edge) --}}
        <button type="button" x-show="currentImages().length > 1" x-cloak @click.stop="galleryPrev()"
            :disabled="galleryIndex() <= 0"
            :class="galleryIndex() <= 0 ? 'opacity-30 cursor-not-allowed' : 'opacity-90 hover:opacity-100'"
            class="absolute left-3 top-1/2 -translate-y-1/2 z-10 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 shadow-soft border border-gray-200 dark:border-gray-700 transition flex items-center justify-center"
            aria-label="Previous image">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
        <button type="button" x-show="currentImages().length > 1" x-cloak @click.stop="galleryNext()"
            :disabled="galleryIndex() >= currentImages().length - 1"
            :class="galleryIndex() >= currentImages().length - 1 ? 'opacity-30 cursor-not-allowed' :
                'opacity-90 hover:opacity-100'"
            class="absolute right-3 top-1/2 -translate-y-1/2 z-10 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 shadow-soft border border-gray-200 dark:border-gray-700 transition flex items-center justify-center"
            aria-label="Next image">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <span x-show="hasUsableImage()"
            class="absolute bottom-3 right-3 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 shadow-soft opacity-0 group-hover:opacity-100 transition">
            <x-ui.icon name="search" class="w-4 h-4" />
        </span>
    </div>
</div>

{{-- Lightbox --}}
<template x-teleport="body">
    <div x-show="lightboxOpen" x-cloak
        class="fixed inset-0 z-[70] bg-black/90 flex items-center justify-center p-4 sm:p-10" role="dialog"
        aria-modal="true" aria-label="Product image" @keydown.escape.window="lightboxOpen = false"
        @keydown.arrow-left.window="if(lightboxOpen) galleryPrev()"
        @keydown.arrow-right.window="if(lightboxOpen) galleryNext()" @click="lightboxOpen = false">
        <button type="button" @click.stop="lightboxOpen = false"
            class="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition"
            aria-label="Close">
            <x-ui.icon name="close" class="w-6 h-6" />
        </button>
        <button x-show="currentImages().length > 1" @click.stop="galleryPrev()" :disabled="galleryIndex() <= 0"
            class="absolute left-4 sm:left-8 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition disabled:opacity-30 disabled:cursor-not-allowed"
            aria-label="Previous image">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
        <button x-show="currentImages().length > 1" @click.stop="galleryNext()"
            :disabled="galleryIndex() >= currentImages().length - 1"
            class="absolute right-4 sm:right-8 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition disabled:opacity-30 disabled:cursor-not-allowed"
            aria-label="Next image">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
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
