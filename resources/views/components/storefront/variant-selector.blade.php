{{-- Shared variant-selector primitive (F.4): the dimension option pills.
     Extracted verbatim from resources/views/storefront/products/show.blade.php.
     Reads the surrounding productDetail(...) Alpine scope through the DOM
     hierarchy: dimensions, selected, dimensionOptions(), updateVariant(). --}}
<template x-for="dimension in dimensions" :key="dimension.code">
    <div class="mt-4">
        <p class="text-sm font-medium mb-2" x-text="dimension.label"></p>
        <div class="flex flex-wrap gap-2">
            <template x-for="option in dimensionOptions(dimension.code)"
                :key="dimension.code + '-' + option">
                <button @click="selected[dimension.code] = option; updateVariant()"
                    :aria-pressed="selected[dimension.code] === option"
                    class="px-3 py-1.5 rounded-full border text-sm transition"
                    :class="selected[dimension.code] === option ?
                        'border-[var(--brand)] text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' :
                        'border-gray-300 dark:border-gray-700 hover:border-gray-400'"
                    x-text="option + (dimension.suffix || '')"></button>
            </template>
        </div>
    </div>
</template>
